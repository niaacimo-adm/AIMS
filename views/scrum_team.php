<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Teams | NIA-ACIMO AIMS</title>
  <?php include '../includes/header.php'; ?>
</head>
<body class="hold-transition sidebar-mini theme-scrum">
<div class="wrapper">
  <?php include '../includes/mainheader.php'; ?>
  <?php include '../includes/sidebar_scrum.php'; ?>
  
  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1>Teams</h1>
          </div>
          <div class="col-sm-6">
            <button class="btn btn-success float-right" id="newTeamBtn">
              <i class="fas fa-plus mr-1"></i> New Team
            </button>
          </div>
        </div>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <div class="row" id="teamsContainer">
          <!-- Teams will be loaded here -->
        </div>
      </div>
    </section>
  </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
$(document).ready(function() {
    localStorage.setItem('currentTheme', 'scrum');
    loadTeams();
    
    $('#newTeamBtn').click(() => {
        Swal.fire({
            title: 'Create New Team',
            html: `
                <input type="text" id="teamName" class="swal2-input" placeholder="Team Name">
                <textarea id="teamDescription" class="swal2-textarea" placeholder="Team Description"></textarea>
            `,
            showCancelButton: true,
            confirmButtonText: 'Create Team',
            preConfirm: () => {
                const name = $('#teamName').val();
                const description = $('#teamDescription').val();
                if (!name) {
                    Swal.showValidationMessage('Please enter team name');
                    return false;
                }
                return { name, description };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                createTeam(result.value);
            }
        });
    });
});

function loadTeams() {
    $.post('../includes/team_ajax.php', {
        action: 'get_teams'
    }, function(response) {
        if (response.success) {
            renderTeams(response.teams);
        }
    }, 'json');
}

function renderTeams(teams) {
    const container = $('#teamsContainer');
    container.empty();
    
    if (teams.length === 0) {
        container.html('<div class="col-12"><div class="alert alert-info">No teams found. Create your first team!</div></div>');
        return;
    }
    
    teams.forEach(team => {
        const teamCard = $(`
            <div class="col-md-4 mb-4">
                <div class="card team-card">
                    <div class="card-header">
                        <h3 class="card-title">${team.team_name}</h3>
                        <div class="card-tools">
                            <button class="btn btn-tool text-primary edit-team" data-team-id="${team.team_id}">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="card-text">${team.description || 'No description'}</p>
                        <div class="team-members">
                            <h6>Members (${team.member_count || 0})</h6>
                            <div class="member-avatars" id="memberAvatars-${team.team_id}">
                                <!-- Members will be loaded here -->
                            </div>
                        </div>
                        <div class="team-projects mt-3">
                            <h6>Projects (${team.project_count || 0})</h6>
                            <div class="project-badges" id="projectBadges-${team.team_id}">
                                <!-- Projects will be loaded here -->
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-sm btn-primary manage-team" data-team-id="${team.team_id}">
                            <i class="fas fa-cog mr-1"></i> Manage
                        </button>
                        <button class="btn btn-sm btn-info view-team" data-team-id="${team.team_id}">
                            <i class="fas fa-eye mr-1"></i> View
                        </button>
                    </div>
                </div>
            </div>
        `);
        
        container.append(teamCard);
        loadTeamMembers(team.team_id);
        loadTeamProjects(team.team_id);
    });
    
    // Add event listeners
    $('.manage-team').click(function() {
        const teamId = $(this).data('team-id');
        manageTeam(teamId);
    });
    
    $('.view-team').click(function() {
        const teamId = $(this).data('team-id');
        viewTeam(teamId);
    });
}

function loadTeamMembers(teamId) {
    $.post('../includes/team_ajax.php', {
        action: 'get_team_members',
        team_id: teamId
    }, function(response) {
        if (response.success) {
            const container = $(`#memberAvatars-${teamId}`);
            container.empty();
            
            response.members.forEach(member => {
                const avatar = member.picture ? 
                    `<img src="../dist/img/employees/${member.picture}" class="avatar" title="${member.first_name} ${member.last_name}">` :
                    `<div class="avatar default-avatar" title="${member.first_name} ${member.last_name}">${member.first_name[0]}${member.last_name[0]}</div>`;
                
                container.append(avatar);
            });
        }
    }, 'json');
}

function loadTeamProjects(teamId) {
    $.post('../includes/team_ajax.php', {
        action: 'get_team_projects',
        team_id: teamId
    }, function(response) {
        if (response.success) {
            const container = $(`#projectBadges-${teamId}`);
            container.empty();
            
            response.projects.forEach(project => {
                container.append(`<span class="badge badge-primary mr-1">${project.project_code}</span>`);
            });
        }
    }, 'json');
}

function createTeam(teamData) {
    $.post('../includes/team_ajax.php', {
        action: 'create_team',
        team_name: teamData.name,
        description: teamData.description
    }, function(response) {
        if (response.success) {
            loadTeams();
            Swal.fire('Success', 'Team created successfully', 'success');
        } else {
            Swal.fire('Error', response.error || 'Failed to create team', 'error');
        }
    }, 'json');
}

function manageTeam(teamId) {
    window.location.href = `team_management.php?team_id=${teamId}`;
}

function viewTeam(teamId) {
    window.location.href = `team_details.php?team_id=${teamId}`;
}
</script>

<style>
.team-card {
    transition: transform 0.2s;
}
.team-card:hover {
    transform: translateY(-2px);
}
.avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    margin-right: 5px;
    object-fit: cover;
}
.default-avatar {
    background: #007bff;
    color: white;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 12px;
}
.member-avatars, .project-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
}
</style>
</body>
</html>