<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

if (!hasPermission('manage_ia_profiles')) {
    header('Location: ../unauthorized.php');
    exit();
}

$id = $_GET['id'] ?? 0;
if (empty($id)) { header('Location: ia_profiles.php'); exit(); }

$database = new Database();
$db = $database->getConnection();

$query = "SELECT * FROM ia_profiles WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->bind_param('i', $id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();

if (!$profile) { header('Location: ia_profiles.php'); exit(); }

$page_title = "LIPA Records – " . htmlspecialchars($profile['ia_name']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $page_title ?> - NIA ACIMO</title>
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap4.min.css">
    <style>
    :root {
        --ia-accent:      #1a5c38;
        --ia-accent-lt:   #24e78f;
        --ia-accent-dim:  rgba(26,92,56,.10);
        --surface:        #ffffff;
        --surface-alt:    #f4f7f6;
        --surface-border: #dee2e6;
        --tx-primary:     #1a2e1e;
        --tx-secondary:   #495057;
        --tx-muted:       #6c757d;
        --tbl-head:       #f0f4f2;
        --tbl-border:     #e9ecef;
        --hero-bg:        #0b1f17;
        --stat-border:    #1a5c38;

        /* Input */
        --input-bg:       #ffffff;
        --input-border:   #ced4da;
        --input-color:    #212529;

        /* Modal */
        --modal-bg:       #ffffff;
        --modal-hd-bg:    #1a5c38;
        --modal-hd-color: #ffffff;
    }
    body.dark-mode {
        --surface:        #1e2d24;
        --surface-alt:    #172218;
        --surface-border: #2d4035;
        --tx-primary:     #d4f5e5;
        --tx-secondary:   #a8c4b0;
        --tx-muted:       #6b8f78;
        --tbl-head:       #172218;
        --tbl-border:     #2d4035;
        --stat-border:    #24e78f;

        --input-bg:       #172218;
        --input-border:   #2d4035;
        --input-color:    #d4f5e5;

        --modal-bg:       #1e2d24;
        --modal-hd-bg:    #0f2d1e;
        --modal-hd-color: #d4f5e5;
    }
    body.dark-mode, body.dark-mode .content-wrapper { background-color: var(--surface-alt) !important; color: var(--tx-primary) !important; }
    body.dark-mode .card { background: var(--surface) !important; border-color: var(--surface-border) !important; color: var(--tx-primary) !important; }
    body.dark-mode .modal-content { background: var(--modal-bg) !important; color: var(--tx-primary) !important; }
    body.dark-mode .modal-header  { background: var(--modal-hd-bg) !important; color: var(--modal-hd-color) !important; border-color: var(--surface-border) !important; }
    body.dark-mode .modal-body    { background: var(--modal-bg) !important; color: var(--tx-primary) !important; }
    body.dark-mode .modal-footer  { background: var(--modal-bg) !important; border-color: var(--surface-border) !important; }
    body.dark-mode .close         { color: var(--modal-hd-color) !important; }
    body.dark-mode .form-control  { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
    body.dark-mode select.form-control option { background: var(--input-bg) !important; color: var(--input-color) !important; }
    body.dark-mode label { color: var(--tx-primary) !important; }
    body.dark-mode .modal label { color: var(--tx-muted) !important; }
    body.dark-mode .modal-section-title { color: var(--ia-accent-lt) !important; }

    .lipa-hero {
        background: var(--hero-bg);
        padding: 24px 28px 44px;
        color: #fff;
    }
    .lipa-hero-name { font-size: 1.5rem; font-weight: 800; margin: 0 0 4px; }
    .lipa-hero-sub  { color: rgba(212,245,229,.7); font-size: .88rem; margin: 0; }

    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 1rem;
        margin: 1.5rem 0;
    }
    .stat-card {
        background: var(--surface);
        border: 1px solid var(--surface-border);
        border-radius: 12px;
        padding: 1.1rem 1rem;
        text-align: center;
        border-top: 3px solid var(--stat-border);
        box-shadow: 0 2px 8px rgba(0,0,0,.06);
    }
    .stat-number { font-size: 1.6rem; font-weight: 800; color: var(--ia-accent); display: block; }
    body.dark-mode .stat-number { color: var(--ia-accent-lt) !important; }
    .stat-label { font-size: 11px; color: var(--tx-muted); text-transform: uppercase; letter-spacing: .6px; margin-top: 4px; }

    .filter-bar {
        background: var(--surface);
        border: 1px solid var(--surface-border);
        border-radius: 12px;
        padding: 1rem;
        display: flex;
        flex-wrap: wrap;
        gap: .75rem;
        align-items: flex-end;
        margin-bottom: 1.25rem;
    }
    .filter-bar .form-group { margin-bottom: 0; min-width: 150px; }
    .filter-bar label { font-size: 11.5px; font-weight: 700; text-transform: uppercase; color: var(--tx-muted); margin-bottom: 4px; }

    .lipa-table th { font-size: 11.5px; text-transform: uppercase; letter-spacing: .3px; background: var(--tbl-head) !important; color: var(--tx-secondary); white-space: nowrap; }
    .lipa-table td { font-size: 13px; vertical-align: middle; }
    .sector-badge { background: rgba(26,92,56,.1); color: var(--ia-accent); border-radius: 12px; padding: 2px 10px; font-size: 11.5px; font-weight: 700; }
    body.dark-mode .sector-badge { background: rgba(36,231,143,.12) !important; color: var(--ia-accent-lt) !important; }

    .btn-lipa { background: linear-gradient(135deg, var(--ia-accent), #2d7a50); color:#fff; border:none; border-radius:8px; padding:8px 16px; font-size:.85rem; font-weight:700; }
    .btn-lipa:hover { color:#fff; opacity:.92; }

    /* ============================================================
       MODAL STYLES — matched to ia_profiles.php
       ============================================================ */
    .modal-header-ia {
        background: linear-gradient(135deg, var(--ia-accent), #2d7a50);
        border-radius: 6px 6px 0 0;
    }
    body.dark-mode .modal-header-ia { background: linear-gradient(135deg,#0b1f17,#1a5c38) !important; }

    .modal-content { border: none; border-radius: 10px; overflow: hidden; }
    body.dark-mode .modal-content { box-shadow: 0 8px 40px rgba(0,0,0,.55) !important; }

    .modal-section-title {
        font-size: 13px;
        font-weight: 700;
        letter-spacing: .6px;
        text-transform: uppercase;
        color: var(--ia-accent);
        border-bottom: 2px solid var(--ia-accent-dim);
        padding-bottom: 6px;
        margin: 1rem 0 .75rem;
    }
    .modal-section-title:first-child { margin-top: 0; }

    .modal .form-control {
        background: var(--input-bg);
        color: var(--input-color);
        border: 1px solid var(--input-border);
        border-radius: 7px;
        font-size: 13.5px;
        transition: border-color .2s, box-shadow .2s;
    }
    .modal .form-control:focus {
        border-color: var(--ia-accent);
        box-shadow: 0 0 0 3px rgba(26,92,56,.15);
    }
    body.dark-mode .modal .form-control:focus { border-color: var(--ia-accent-lt) !important; box-shadow: 0 0 0 3px rgba(36,231,143,.15) !important; }

    .modal label { font-size: 12.5px; font-weight: 600; color: var(--tx-secondary); margin-bottom: 4px; }
    body.dark-mode .modal label { color: var(--tx-muted) !important; }

    .required-star { color: #dc3545; }

    /* DataTables theming to match the LIPA card */
    #lipaTable_wrapper { padding: 1rem 1rem 0.25rem; }
    #lipaTable_wrapper .dataTables_length select,
    #lipaTable_wrapper .dataTables_filter input {
        border: 1px solid var(--surface-border);
        border-radius: 6px;
        background: var(--surface);
        color: var(--tx-primary);
        padding: 4px 8px;
        font-size: 13px;
    }
    #lipaTable_wrapper .dataTables_length,
    #lipaTable_wrapper .dataTables_filter,
    #lipaTable_wrapper .dataTables_info,
    #lipaTable_wrapper .dataTables_paginate { font-size: 12.5px; color: var(--tx-muted); }
    #lipaTable_wrapper .dataTables_filter { float: right; }
    #lipaTable_wrapper .dataTables_filter input { margin-left: 6px; }
    #lipaTable_wrapper .page-item.active .page-link {
        background: var(--ia-accent) !important;
        border-color: var(--ia-accent) !important;
        color: #fff !important;
    }
    #lipaTable_wrapper .page-link { color: var(--ia-accent); }
    body.dark-mode #lipaTable_wrapper .page-link { background: var(--surface); border-color: var(--surface-border); }
    body.dark-mode #lipaTable_wrapper .dataTables_length select,
    body.dark-mode #lipaTable_wrapper .dataTables_filter input { background: var(--surface-alt); border-color: var(--surface-border); color: var(--tx-primary); }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar_ia.php'; ?>

    <div class="content-wrapper">
        <div class="lipa-hero">
            <nav aria-label="breadcrumb" style="margin-bottom:8px;">
                <ol class="breadcrumb" style="background:transparent;padding:0;margin:0;">
                    <li class="breadcrumb-item"><a href="dashboard.php" style="color:rgba(212,245,229,.6);">Home</a></li>
                    <li class="breadcrumb-item"><a href="ia_profiles.php" style="color:rgba(212,245,229,.6);">IA Profiles</a></li>
                    <li class="breadcrumb-item"><a href="ia_profile_view.php?id=<?= $id ?>" style="color:rgba(212,245,229,.6);"><?= htmlspecialchars($profile['ia_name']) ?></a></li>
                    <li class="breadcrumb-item active" style="color:rgba(212,245,229,.85);">LIPA Records</li>
                </ol>
            </nav>
            <div class="lipa-hero-name"><i class="fas fa-seedling mr-2" style="color:#24e78f;"></i>LIPA Records</div>
            <div class="lipa-hero-sub"><?= htmlspecialchars($profile['ia_name']) ?> — List of Irrigated and Planted Area</div>
        </div>

        <section class="content" style="padding-top:0;">
            <div class="container-fluid">

                <div class="stats-row" id="statsRow">
                    <div class="stat-card"><span class="stat-number" id="statFarmers">0</span><div class="stat-label">Farmers / Lots</div></div>
                    <div class="stat-card"><span class="stat-number" id="statServiceArea">0.00</span><div class="stat-label">Service Area (ha)</div></div>
                    <div class="stat-card"><span class="stat-number" id="statIrrigatedArea">0.00</span><div class="stat-label">Irrigated/Planted (ha)</div></div>
                    <div class="stat-card"><span class="stat-number" id="statSectors">0</span><div class="stat-label">Sectors</div></div>
                </div>

                <div class="filter-bar">
                    <div class="form-group">
                        <label>Crop Year</label>
                        <select class="form-control" id="fCropYear"><option value="">All</option></select>
                    </div>
                    <div class="form-group">
                        <label>Season</label>
                        <select class="form-control" id="fSeason">
                            <option value="">All</option>
                            <option value="wet">Wet</option>
                            <option value="dry">Dry</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Sector</label>
                        <select class="form-control" id="fSector"><option value="">All</option></select>
                    </div>
                    <div class="form-group">
                        <button class="btn btn-outline-secondary" id="btnFilter"><i class="fas fa-filter mr-1"></i>Apply</button>
                    </div>
                    <div class="form-group ml-auto" style="display:flex;gap:8px;">
                        <button class="btn-lipa" onclick="exportLipaReport()"><i class="fas fa-file-excel mr-1"></i>Export Report</button>
                        <button class="btn-lipa" data-toggle="modal" data-target="#importLipaModal"><i class="fas fa-file-upload mr-1"></i>Import LIPA</button>
                        <button class="btn-lipa" data-toggle="modal" data-target="#addLipaModal" onclick="resetLipaForm()"><i class="fas fa-plus mr-1"></i>Add Entry</button>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover lipa-table mb-0" id="lipaTable">
                                <thead>
                                    <tr>
                                        <th>Sector</th>
                                        <th>Lot No.</th>
                                        <th>Landowner / Tiller</th>
                                        <th>Service Area (ha)</th>
                                        <th>Irrigated/Planted (ha)</th>
                                        <th>Variety</th>
                                        <th>Date Sown</th>
                                        <th>Date Planted</th>
                                        <th>Expected Harvest</th>
                                        <th>RSBSA Reg No.</th>
                                        <th>Remarks</th>
                                        <th style="width:90px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="lipaTableBody">
                                    <tr><td colspan="12" class="text-center text-muted py-4">Loading…</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </section>
    </div>

    <?php include '../includes/mainfooter.php'; ?>
</div>


<!-- ================================================================
     MODAL — IMPORT LIPA FILE
     ================================================================ -->
<div class="modal fade" id="importLipaModal" tabindex="-1" role="dialog" aria-labelledby="importLipaModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header modal-header-ia">
        <h5 class="modal-title text-white" id="importLipaModalLabel">
          <i class="fas fa-file-upload mr-2"></i>Import LIPA File
        </h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form id="importLipaForm" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="action" value="import">
        <input type="hidden" name="ia_profile_id" value="<?= $id ?>">

        <div class="modal-body" style="padding: 1.5rem;">

          <div class="modal-section-title">
            <i class="fas fa-calendar-alt mr-1"></i> Period
          </div>
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label>Crop Year <span class="required-star">*</span></label>
                <input type="number" class="form-control" name="crop_year" min="2000" max="2100" value="<?= date('Y') ?>" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Season <span class="required-star">*</span></label>
                <select class="form-control" name="season" required>
                  <option value="wet">Wet</option>
                  <option value="dry">Dry</option>
                </select>
              </div>
            </div>
          </div>

          <div class="modal-section-title">
            <i class="fas fa-file-excel mr-1"></i> File
          </div>
          <div class="row">
            <div class="col-12">
              <div class="form-group">
                <label>LIPA Excel File (.xlsx) <span class="required-star">*</span></label>
                <input type="file" class="form-control-file" name="lipa_file" accept=".xlsx,.xls" required>
                <small class="form-text text-muted">One sheet per sector, same layout as the LIPA template. Rows are recognized by a numbered "No." column, so titles, repeated headers, and Subtotal rows are skipped automatically.</small>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-12">
              <div class="form-group form-check">
                <input type="checkbox" class="form-check-input" id="replaceExisting" name="replace_existing" value="1">
                <label class="form-check-label" for="replaceExisting">Replace existing records for this crop year/season (instead of adding on top)</label>
              </div>
            </div>
          </div>

        </div><!-- /.modal-body -->

        <div class="modal-footer" style="border-top: 1px solid var(--surface-border);">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">
            <i class="fas fa-times mr-1"></i> Cancel
          </button>
          <button type="submit" class="btn btn-success">
            <i class="fas fa-upload mr-1"></i> Import
          </button>
        </div>
      </form>
    </div>
  </div>
</div>


<!-- ================================================================
     MODAL — ADD / EDIT LIPA ENTRY
     ================================================================ -->
<div class="modal fade" id="addLipaModal" tabindex="-1" role="dialog" aria-labelledby="lipaModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header modal-header-ia">
        <h5 class="modal-title text-white" id="lipaModalTitle">
          <i class="fas fa-plus mr-2"></i><span id="lipaModalTitleText">Add LIPA Entry</span>
        </h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form id="lipaEntryForm" novalidate>
        <input type="hidden" name="action" id="formAction" value="add">
        <input type="hidden" name="id" id="entryId">
        <input type="hidden" name="ia_profile_id" value="<?= $id ?>">

        <div class="modal-body" style="padding: 1.5rem;">

          <!-- ── Section: Period & Sector ── -->
          <div class="modal-section-title">
            <i class="fas fa-calendar-alt mr-1"></i> Period &amp; Sector
          </div>
          <div class="row">
            <div class="col-md-3">
              <div class="form-group">
                <label>Crop Year <span class="required-star">*</span></label>
                <input type="number" class="form-control" name="crop_year" required value="<?= date('Y') ?>">
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label>Season <span class="required-star">*</span></label>
                <select class="form-control" name="season" required>
                  <option value="wet">Wet</option>
                  <option value="dry">Dry</option>
                </select>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label>Sector <span class="required-star">*</span></label>
                <input type="text" class="form-control" name="sector" placeholder="e.g. SECTOR 1" required>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label>Lot No.</label>
                <input type="text" class="form-control" name="lot_no" placeholder="e.g. 12-A">
              </div>
            </div>
          </div>

          <!-- ── Section: Landowner / Tiller ── -->
          <div class="modal-section-title">
            <i class="fas fa-user mr-1"></i> Landowner / Tiller
          </div>
          <div class="row">
            <div class="col-md-4">
              <div class="form-group">
                <label>First Name</label>
                <input type="text" class="form-control" name="landowner_first_name" placeholder="First name">
              </div>
            </div>
            <div class="col-md-2">
              <div class="form-group">
                <label>M.I.</label>
                <input type="text" class="form-control" name="landowner_mi" maxlength="5">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Last Name</label>
                <input type="text" class="form-control" name="landowner_last_name" placeholder="Last name">
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label>RSBSA Reg No.</label>
                <input type="text" class="form-control" name="rsbsa_reg_no">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Crop Insurance</label>
                <input type="text" class="form-control" name="crop_insurance">
              </div>
            </div>
          </div>

          <!-- ── Section: Area & Variety ── -->
          <div class="modal-section-title">
            <i class="fas fa-seedling mr-1"></i> Area &amp; Variety
          </div>
          <div class="row">
            <div class="col-md-3">
              <div class="form-group">
                <label>Service Area (ha)</label>
                <input type="number" step="0.01" min="0" class="form-control" name="service_area_ha" placeholder="0.00">
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label>Irrigated/Planted (ha)</label>
                <input type="number" step="0.01" min="0" class="form-control" name="irrigated_planted_area_ha" placeholder="0.00">
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label>Variety (Inbred)</label>
                <input type="text" class="form-control" name="variety_inbred">
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label>Variety (Hybrid)</label>
                <input type="text" class="form-control" name="variety_hybrid">
              </div>
            </div>
          </div>

          <!-- ── Section: Key Dates ── -->
          <div class="modal-section-title">
            <i class="fas fa-clock mr-1"></i> Key Dates
          </div>
          <div class="row">
            <div class="col-md-4">
              <div class="form-group">
                <label>Date Sown</label>
                <input type="date" class="form-control" name="date_sown">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>Date Planted</label>
                <input type="date" class="form-control" name="date_planted">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>Expected Harvest</label>
                <input type="date" class="form-control" name="expected_harvest_date">
              </div>
            </div>
          </div>

          <!-- ── Section: Remarks ── -->
          <div class="modal-section-title">
            <i class="fas fa-file-alt mr-1"></i> Remarks
          </div>
          <div class="row">
            <div class="col-12">
              <div class="form-group">
                <label>Remarks / Farmer's Confirmation</label>
                <textarea class="form-control" name="remarks" rows="2" placeholder="Any additional notes…"></textarea>
              </div>
            </div>
          </div>

        </div><!-- /.modal-body -->

        <div class="modal-footer" style="border-top: 1px solid var(--surface-border);">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">
            <i class="fas fa-times mr-1"></i> Cancel
          </button>
          <button type="submit" class="btn btn-success">
            <i class="fas fa-save mr-1"></i> <span id="lipaSaveBtnText">Save Entry</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap4.min.js"></script>
<script>
const IA_PROFILE_ID = <?= json_encode((int)$id) ?>;
const AJAX_URL = '../includes/ia_lipa_ajax.php';
let lipaDataTable = null;

function currentFilters() {
    return {
        ia_profile_id: IA_PROFILE_ID,
        crop_year: $('#fCropYear').val(),
        season: $('#fSeason').val(),
        sector: $('#fSector').val()
    };
}

function exportLipaReport() {
    const farmerCount = parseInt($('#statFarmers').text(), 10) || 0;
    if (farmerCount === 0) {
        Swal.fire({ icon: 'info', title: 'Nothing to export', text: 'No LIPA records match the current filters.' });
        return;
    }

    const params = new URLSearchParams(Object.assign({ action: 'export' }, currentFilters()));
    const url = AJAX_URL + '?' + params.toString();

    // Use fetch + blob (instead of window.open) so we can show a loader
    // for the duration of the server-side export (which can take a while
    // for large sheets) and close it precisely on success or failure.
    Swal.fire({
        title: 'Generating report…',
        html: 'Please wait, this may take a moment for large datasets.',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => Swal.showLoading()
    });

    fetch(url)
        .then(async (res) => {
            const contentType = res.headers.get('Content-Type') || '';

            // The server responds with JSON on error (e.g. no matching
            // records, missing dependency, DB error) instead of a file.
            if (!res.ok || contentType.includes('application/json')) {
                const data = await res.json().catch(() => ({}));
                throw new Error(data.message || 'Export failed.');
            }

            // Pull the filename the server generated from the
            // Content-Disposition header so the download matches it.
            const disposition = res.headers.get('Content-Disposition') || '';
            const match = disposition.match(/filename="?([^"]+)"?/);
            const filename = match ? match[1] : 'LIPA_Report.xlsx';

            const blob = await res.blob();
            return { blob, filename };
        })
        .then(({ blob, filename }) => {
            const blobUrl = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = blobUrl;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            a.remove();
            window.URL.revokeObjectURL(blobUrl);
            Swal.close();
        })
        .catch((err) => {
            Swal.fire({ icon: 'error', title: 'Export Failed', text: err.message || 'Could not generate the report.' });
        });
}

function loadFilters() {
    $.get(AJAX_URL, { action: 'get_filters', ia_profile_id: IA_PROFILE_ID }, function (res) {
        if (!res.success) return;
        const years = [...new Set(res.periods.map(p => p.crop_year))];
        const $y = $('#fCropYear').empty().append('<option value="">All</option>');
        years.forEach(y => $y.append(`<option value="${y}">${y}</option>`));

        const $s = $('#fSector').empty().append('<option value="">All</option>');
        res.sectors.forEach(s => $s.append(`<option value="${s}">${s}</option>`));
    }, 'json');
}

function destroyLipaTable() {
    if (lipaDataTable) {
        lipaDataTable.destroy();
        lipaDataTable = null;
    }
}

function loadEntries() {
    destroyLipaTable();
    $('#lipaTableBody').html('<tr><td colspan="12" class="text-center text-muted py-4">Loading…</td></tr>');
    $.get(AJAX_URL, Object.assign({ action: 'list' }, currentFilters()), function (res) {
        if (!res.success) {
            $('#lipaTableBody').html(`<tr><td colspan="12" class="text-center text-danger py-4">${res.message || 'Failed to load records.'}</td></tr>`);
            return;
        }
        renderSummary(res.summary);
        renderRows(res.data);
    }, 'json').fail(() => {
        $('#lipaTableBody').html('<tr><td colspan="12" class="text-center text-danger py-4">Network error.</td></tr>');
    });
}

function renderSummary(s) {
    $('#statFarmers').text(s.farmer_count ?? 0);
    $('#statServiceArea').text(parseFloat(s.total_service_area ?? 0).toFixed(2));
    $('#statIrrigatedArea').text(parseFloat(s.total_irrigated_area ?? 0).toFixed(2));
    $('#statSectors').text(s.sector_count ?? 0);
}

function renderRows(rows) {
    destroyLipaTable();

    if (!rows.length) {
        $('#lipaTableBody').html('<tr><td colspan="12" class="text-center text-muted py-4">No LIPA records found for this filter.</td></tr>');
        return;
    }
    const html = rows.map(r => {
        const name = [r.landowner_first_name, r.landowner_mi, r.landowner_last_name].filter(Boolean).join(' ');
        const variety = r.variety_inbred || r.variety_hybrid || '—';
        return `<tr>
            <td><span class="sector-badge">${escapeHtml(r.sector)}</span></td>
            <td>${escapeHtml(r.lot_no || '—')}</td>
            <td>${escapeHtml(name || '—')}</td>
            <td>${parseFloat(r.service_area_ha).toFixed(2)}</td>
            <td>${parseFloat(r.irrigated_planted_area_ha).toFixed(2)}</td>
            <td>${escapeHtml(variety)}</td>
            <td>${r.date_sown || '—'}</td>
            <td>${r.date_planted || '—'}</td>
            <td>${r.expected_harvest_date || '—'}</td>
            <td>${escapeHtml(r.rsbsa_reg_no || '—')}</td>
            <td>${escapeHtml(r.remarks || '—')}</td>
            <td>
                <button class="btn btn-sm btn-outline-success" onclick="editEntry(${r.id})" title="Edit"><i class="fas fa-edit"></i></button>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteEntry(${r.id})" title="Delete"><i class="fas fa-trash"></i></button>
            </td>
        </tr>`;
    }).join('');
    $('#lipaTableBody').html(html);

    lipaDataTable = $('#lipaTable').DataTable({
        responsive: true,
        order: [[0, 'asc'], [1, 'asc']],
        columnDefs: [{ orderable: false, targets: -1 }],
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
        pageLength: 25,
        language: {
            search: '',
            searchPlaceholder: 'Search records…',
            emptyTable: 'No LIPA records found for this filter.'
        }
    });
}

function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}

function resetLipaForm() {
    $('#lipaEntryForm')[0].reset();
    $('#formAction').val('add');
    $('#entryId').val('');
    $('#lipaModalTitle').html('<i class="fas fa-plus mr-2"></i>Add LIPA Entry');
}

function editEntry(id) {
    $.get(AJAX_URL, { action: 'get_entry', id }, function (res) {
        if (!res.success) { Swal.fire({ icon: 'error', title: 'Error', text: res.message }); return; }
        const d = res.data;
        const form = $('#lipaEntryForm')[0];
        Object.keys(d).forEach(k => {
            const el = form.querySelector(`[name="${k}"]`);
            if (el) el.value = d[k] ?? '';
        });
        $('#formAction').val('update');
        $('#entryId').val(d.id);
        $('#lipaModalTitle').html('<i class="fas fa-edit mr-2"></i>Edit LIPA Entry');
        $('#addLipaModal').modal('show');
    }, 'json');
}

function deleteEntry(id) {
    Swal.fire({
        title: 'Delete this entry?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Yes, Delete',
        reverseButtons: true
    }).then(result => {
        if (!result.isConfirmed) return;
        $.post(AJAX_URL, { action: 'delete', id }, function (res) {
            if (res.success) {
                Swal.fire({ icon: 'success', title: 'Deleted!', timer: 1200, showConfirmButton: false });
                loadEntries();
                loadFilters();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message });
            }
        }, 'json');
    });
}

$(document).ready(function () {
    loadFilters();
    loadEntries();

    $('#btnFilter').on('click', loadEntries);

    $('#lipaEntryForm').on('submit', function (e) {
        e.preventDefault();
        $.post(AJAX_URL, $(this).serialize(), function (res) {
            if (res.success) {
                $('#addLipaModal').modal('hide');
                Swal.fire({ icon: 'success', title: 'Saved!', timer: 1500, showConfirmButton: false });
                loadEntries();
                loadFilters();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message });
            }
        }, 'json').fail(() => Swal.fire({ icon: 'error', title: 'Network Error', text: 'Could not reach the server.' }));
    });

    $('#importLipaForm').on('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        Swal.fire({ title: 'Importing…', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        $.ajax({
            url: AJAX_URL,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(res => {
            if (res.success) {
                Swal.fire({ icon: 'success', title: 'Imported!', text: res.message });
                $('#importLipaModal').modal('hide');
                loadEntries();
                loadFilters();
            } else {
                Swal.fire({ icon: 'error', title: 'Import Failed', text: res.message });
            }
        }).fail((xhr) => {
            console.error('Import failed. HTTP status:', xhr.status);
            console.error('Raw server response:', xhr.responseText);
            // Show the real server response (truncated) instead of a generic message,
            // so PHP fatal errors / warnings are visible instead of hidden.
            const snippet = (xhr.responseText || '').replace(/<[^>]*>/g, ' ').trim().slice(0, 500);
            Swal.fire({
                icon: 'error',
                title: `Server Error (HTTP ${xhr.status})`,
                html: `<div style="text-align:left;font-size:12px;max-height:200px;overflow:auto;white-space:pre-wrap;">${escapeHtml(snippet || 'No response body — check the PHP error log.')}</div>`
            });
        });
    });
});
</script>
</body>
</html>