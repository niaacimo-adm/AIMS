<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <?php include '../includes/header.php'; ?>
    <style>
        body {
            background: linear-gradient(135deg, #ffffffff 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-container {
            max-width: 450px;
            width: 100%;
            padding: 20px;
            margin: auto;
        }
        .login-logo {
            text-align: center;
            margin-bottom: 25px;
            color: black;
        }
        .login-logo h2 {
            font-weight: 700;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            border: none;
            overflow: hidden;
        }
        .card-header {
            background: linear-gradient(to right, #3bd748ff);
            color: white;
            text-align: center;
            padding: 20px;
            border-bottom: none;
        }
        .card-body {
            padding: 30px;
        }
        .password-toggle {
            cursor: pointer;
            background-color: #f8f9fa;
            border: 1px solid #ced4da;
            border-left: none;
        }
        .password-toggle:hover {
            background-color: #e9ecef;
        }
        .btn-primary {
            /* background: linear-gradient(to right, #4bb771ff); */
            border: none;
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .password-strength {
            height: 5px;
            margin-top: 5px;
            border-radius: 5px;
            transition: all 0.3s;
        }
        .progress-bar {
            border-radius: 5px;
        }
        .password-criteria {
            font-size: 0.85rem;
            color: #6c757d;
        }
        .criteria-met {
            color: #28a745;
        }
        .password-match {
            font-size: 0.9rem;
            font-weight: 500;
        }
        .back-link {
            color: #4b6cb7;
            text-decoration: none;
            transition: color 0.2s;
        }
        .back-link:hover {
            color: #182848;
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div class="login-container">
    <div class="login-logo">
        <h2><i class="fas fa-lock"></i> Password Reset</h2>
        <p class="mb-0">Enter your new password below</p>
    </div>
    <div class="card">
        <div class="card-header">
            <h4 class="mb-0"><i class="fas fa-key"></i> Set New Password</h4>
        </div>
        <div class="card-body">
            <div id="messageContainer">
                <!-- Messages will be displayed here -->
            </div>
            
            <form id="resetPasswordForm" method="post">
                <div class="mb-3">
                    <label for="new_password" class="form-label">New Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8" placeholder="Enter new password">
                        <span class="input-group-text password-toggle" onclick="togglePassword('new_password')">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                    <div class="password-criteria mt-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="lengthCheck" disabled>
                            <label class="form-check-label" for="lengthCheck">At least 8 characters</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="numberCheck" disabled>
                            <label class="form-check-label" for="numberCheck">Contains a number</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="specialCheck" disabled>
                            <label class="form-check-label" for="specialCheck">Contains a special character</label>
                        </div>
                    </div>
                    <div class="password-strength mt-2">
                        <div class="progress" style="height: 8px;">
                            <div id="passwordStrengthBar" class="progress-bar" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <small id="passwordStrengthText" class="text-muted">Password strength: Very weak</small>
                    </div>
                </div>
                <br>
                <div class="mb-4">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8" placeholder="Confirm new password">
                        <span class="input-group-text password-toggle" onclick="togglePassword('confirm_password')">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                    <div id="passwordMatch" class="password-match mt-2"></div>
                </div>
                
                <input type="hidden" id="token" name="token" value="">
                <button type="submit" class="btn btn-primary w-100 py-2">
                    <i class="fas fa-sync-alt me-2"></i> Reset Password
                </button>
            </form>
            
            <div class="text-center mt-4">
                <a href="../views/profile.php" class="back-link">
                    <i class="fas fa-arrow-left me-1"></i> Back to Login
                </a>
            </div>
        </div>
    </div>
    
</div>
<?php include '../includes/footer.php'; ?>
<script>
    // Get token from URL
    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get('token');
    document.getElementById('token').value = token || '';
    
    // Check if token is present
    if (!token) {
        showMessage('Invalid or missing reset token. Please request a new password reset link.', 'danger');
        document.getElementById('resetPasswordForm').style.display = 'none';
    } else {
        // Validate token format (basic check)
        if (token.length < 20) {
            showMessage('Invalid reset token format. Please request a new password reset link.', 'danger');
            document.getElementById('resetPasswordForm').style.display = 'none';
        } else {
            showMessage('Please enter your new password below. Your token is valid.', 'info');
        }
    }
    
    // Password strength calculation
    const newPasswordInput = document.getElementById('new_password');
    const strengthBar = document.getElementById('passwordStrengthBar');
    const strengthText = document.getElementById('passwordStrengthText');
    const lengthCheck = document.getElementById('lengthCheck');
    const numberCheck = document.getElementById('numberCheck');
    const specialCheck = document.getElementById('specialCheck');
    
    newPasswordInput.addEventListener('input', function() {
        const password = this.value;
        let strength = 0;
        
        // Check password length
        if (password.length >= 8) {
            strength += 25;
            lengthCheck.classList.add('criteria-met');
            lengthCheck.checked = true;
        } else {
            lengthCheck.classList.remove('criteria-met');
            lengthCheck.checked = false;
        }
        
        // Check for numbers
        if (/\d/.test(password)) {
            strength += 25;
            numberCheck.classList.add('criteria-met');
            numberCheck.checked = true;
        } else {
            numberCheck.classList.remove('criteria-met');
            numberCheck.checked = false;
        }
        
        // Check for special characters
        if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) {
            strength += 25;
            specialCheck.classList.add('criteria-met');
            specialCheck.checked = true;
        } else {
            specialCheck.classList.remove('criteria-met');
            specialCheck.checked = false;
        }
        
        // Check for uppercase and lowercase letters
        if (/[a-z]/.test(password) && /[A-Z]/.test(password)) {
            strength += 25;
        }
        
        // Update strength bar
        strengthBar.style.width = strength + '%';
        
        // Update strength text and color
        if (strength === 0) {
            strengthText.textContent = 'Password strength: Very weak';
            strengthBar.className = 'progress-bar bg-danger';
        } else if (strength <= 25) {
            strengthText.textContent = 'Password strength: Weak';
            strengthBar.className = 'progress-bar bg-danger';
        } else if (strength <= 50) {
            strengthText.textContent = 'Password strength: Fair';
            strengthBar.className = 'progress-bar bg-warning';
        } else if (strength <= 75) {
            strengthText.textContent = 'Password strength: Good';
            strengthBar.className = 'progress-bar bg-info';
        } else {
            strengthText.textContent = 'Password strength: Strong';
            strengthBar.className = 'progress-bar bg-success';
        }
    });
    
    // Password matching validation
    const confirmPassword = document.getElementById('confirm_password');
    const passwordMatch = document.getElementById('passwordMatch');
    
    confirmPassword.addEventListener('input', function() {
        const newPassword = document.getElementById('new_password').value;
        
        if (this.value && newPassword) {
            if (this.value === newPassword) {
                passwordMatch.innerHTML = '<i class="fas fa-check-circle text-success"></i> Passwords match!';
            } else {
                passwordMatch.innerHTML = '<i class="fas fa-times-circle text-danger"></i> Passwords do not match!';
            }
        } else {
            passwordMatch.innerHTML = '';
        }
    });
    
    // Form submission
    document.getElementById('resetPasswordForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        
        if (newPassword !== confirmPassword) {
            showMessage('Passwords do not match. Please make sure both fields are identical.', 'danger');
            return;
        }
        
        if (newPassword.length < 8) {
            showMessage('Password must be at least 8 characters long.', 'danger');
            return;
        }
        
        // Check password strength
        const strength = calculatePasswordStrength(newPassword);
        if (strength < 50) {
            showMessage('Your password is too weak. Please use a stronger password with a mix of letters, numbers, and special characters.', 'warning');
            return;
        }
        
        // Simulate form submission (in a real scenario, this would be an AJAX call to the server)
        simulateResetPassword(token, newPassword);
    });
    
    // Calculate password strength
    function calculatePasswordStrength(password) {
        let strength = 0;
        
        if (password.length >= 8) strength += 25;
        if (/\d/.test(password)) strength += 25;
        if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) strength += 25;
        if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength += 25;
        
        return strength;
    }
    
    // Simulate password reset (for demo purposes)
    function simulateResetPassword(token, newPassword) {
        showMessage('Processing your request...', 'info');
        
        // Simulate server request delay
        setTimeout(() => {
            // This is a simulation - in a real application, this would be an AJAX call
            if (token && token.length > 10) {
                showMessage('Password has been reset successfully! You can now <a href="login.php" class="alert-link">login</a> with your new password.', 'success');
                document.getElementById('resetPasswordForm').reset();
                document.getElementById('resetPasswordForm').style.display = 'none';
                
                // Reset strength indicators
                strengthBar.style.width = '0%';
                strengthText.textContent = 'Password strength: Very weak';
                strengthBar.className = 'progress-bar bg-danger';
                passwordMatch.innerHTML = '';
                
                // Reset checkboxes
                lengthCheck.checked = false;
                numberCheck.checked = false;
                specialCheck.checked = false;
                lengthCheck.classList.remove('criteria-met');
                numberCheck.classList.remove('criteria-met');
                specialCheck.classList.remove('criteria-met');
            } else {
                showMessage('Invalid or expired reset token. Please request a new password reset link.', 'danger');
            }
        }, 2000);
    }
    
    // Helper function to show messages
    function showMessage(message, type) {
        const messageContainer = document.getElementById('messageContainer');
        messageContainer.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
            <i class="${type === 'success' ? 'fas fa-check-circle' : type === 'danger' ? 'fas fa-exclamation-circle' : 'fas fa-info-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>`;
    }
    
    // Toggle password visibility
    function togglePassword(inputId) {
        const input = document.getElementById(inputId);
        const icon = input.parentNode.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
</script>

</body>
</html>