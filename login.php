<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in to CodeHub APS · CodeHub APS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Identity Services -->
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <meta name="google-signin-client_id" content="163339139019-r9gecb5pif63c3k7tc0ed0as9n34hj7l.apps.googleusercontent.com">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
        }
        
        .input-focus {
            transition: all 0.3s ease;
        }
        
        .input-focus:focus {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .shake {
            animation: shake 0.5s;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .pulse-border {
            animation: pulse-border 2s infinite;
        }
        
        @keyframes pulse-border {
            0% { box-shadow: 0 0 0 0 rgba(9, 105, 218, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(9, 105, 218, 0); }
            100% { box-shadow: 0 0 0 0 rgba(9, 105, 218, 0); }
        }
        
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .password-strength {
            height: 4px;
            border-radius: 2px;
            transition: all 0.3s ease;
        }
        
        .loading-spinner {
            border: 2px solid #f3f3f3;
            border-top: 2px solid #0969da;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .tooltip {
            visibility: hidden;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .tooltip-trigger:hover .tooltip {
            visibility: visible;
            opacity: 1;
        }
        
        .social-button {
            transition: all 0.3s ease;
        }
        
        .social-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .animate-blob {
            animation: blob 7s infinite;
        }
        
        @keyframes blob {
            0% { transform: translate(0px, 0px) scale(1); }
            33% { transform: translate(30px, -50px) scale(1.1); }
            66% { transform: translate(-20px, 20px) scale(0.9); }
            100% { transform: translate(0px, 0px) scale(1); }
        }
        
        .animation-delay-2000 {
            animation-delay: 2s;
        }
        
        .animation-delay-4000 {
            animation-delay: 4s;
        }
        
        .google-custom-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 10px 16px;
            border: 1px solid #dadce0;
            border-radius: 4px;
            background-color: #fff;
            color: #3c4043;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .google-custom-btn:hover {
            box-shadow: 0 1px 2px 0 rgba(60, 64, 67, 0.3), 0 1px 3px 1px rgba(60, 64, 67, 0.15);
            background-color: #f8f9fa;
        }
        
        .google-custom-btn:active {
            background-color: #f1f3f4;
        }
        
        /* Google button container */
        .g_id_signin {
            width: 100% !important;
        }
        
        /* Custom Google button styling */
        .custom-google-btn {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 100% !important;
            height: 42px !important;
            border: 1px solid #dadce0 !important;
            border-radius: 4px !important;
            background: white !important;
            cursor: pointer !important;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
    <!-- Background Pattern -->
    <div class="fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute -top-40 -right-40 w-80 h-80 bg-purple-300 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-blob"></div>
        <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-blue-300 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-blob animation-delay-2000"></div>
        <div class="absolute top-40 left-40 w-80 h-80 bg-pink-300 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-blob animation-delay-4000"></div>
    </div>

    <!-- Main Container -->
    <div class="w-full max-w-md fade-in">
        <!-- Logo and Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center mb-6">
                <svg class="h-12 w-12 text-gray-900" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">Sign in to CodeHub APS</h1>
            <p class="text-gray-600 mt-2">Welcome back! Please enter your details.</p>
        </div>

        <!-- Login Form -->
        <div class="bg-white rounded-xl shadow-lg p-8">
            <!-- Error/Success Messages -->
            <div id="errorMessage" class="hidden mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg fade-in">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <span id="errorText">Invalid credentials</span>
                </div>
            </div>
            
            <div id="successMessage" class="hidden mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg fade-in">
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    <span id="successText">Login successful! Redirecting...</span>
                </div>
            </div>

            <form id="loginForm" class="space-y-5">
                <!-- Email Field -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                        Email address
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-envelope text-gray-400"></i>
                        </div>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               required
                               class="input-focus w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Enter your email">
                    </div>
                    <span class="text-xs text-red-500 mt-1 hidden" id="emailError">Email is required</span>
                </div>

                <!-- Password Field -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                        Password
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-lock text-gray-400"></i>
                        </div>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               required
                               class="input-focus w-full pl-10 pr-10 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Enter your password">
                        <button type="button" 
                                id="togglePassword"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <i class="fas fa-eye text-gray-400 hover:text-gray-600" id="eyeIcon"></i>
                        </button>
                    </div>
                    <span class="text-xs text-red-500 mt-1 hidden" id="passwordError">Password is required</span>
                </div>

                <!-- Remember Me & Forgot Password -->
                <div class="flex items-center justify-between">
                    <label class="flex items-center">
                        <input type="checkbox" 
                               id="remember" 
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <span class="ml-2 text-sm text-gray-600">Remember me</span>
                    </label>
                    <a href="#" class="text-sm text-blue-600 hover:text-blue-700 font-medium">
                        Forgot password?
                    </a>
                </div>

                <!-- 2FA Option -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                    <div class="flex items-center">
                        <i class="fas fa-shield-alt text-blue-600 mr-2"></i>
                        <span class="text-sm text-blue-700">
                            Two-factor authentication available
                        </span>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" 
                        id="submitBtn"
                        class="w-full bg-blue-600 text-white font-semibold py-2.5 px-4 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all transform hover:scale-[1.02] flex items-center justify-center">
                    <span id="btnText">Sign in</span>
                    <div id="btnLoader" class="loading-spinner ml-2 hidden"></div>
                </button>
            </form>

            <!-- Divider -->
            <div class="relative my-6">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-center text-sm">
                    <span class="px-2 bg-white text-gray-500">Or continue with</span>
                </div>
            </div>

            <!-- Social Login Options -->
            <div class="grid grid-cols-2 gap-3">
                <!-- Google Sign-In Button -->
                <div id="g_id_onload"
                     data-client_id="163339139019-r9gecb5pif63c3k7tc0ed0as9n34hj7l.apps.googleusercontent.com"
                     data-context="signin"
                     data-ux_mode="popup"
                     data-callback="handleGoogleSignIn"
                     data-auto_prompt="false">
                </div>

                <div class="g_id_signin"
                     data-type="standard"
                     data-shape="rectangular"
                     data-theme="outline"
                     data-text="signin_with"
                     data-size="large"
                     data-logo_alignment="left"
                     data-width="300">
                </div>
                
                <button class="social-button flex items-center justify-center px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-all">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                    </svg>
                    <span class="text-sm font-medium text-gray-700">CodeHub APS</span>
                </button>
            </div>

            <!-- Sign Up Link -->
            <div class="mt-6 text-center">
                <span class="text-sm text-gray-600">
                    New to CodeHub APS? 
                    <a href="#" id="openRegisterModal" class="text-blue-600 hover:text-blue-700 font-semibold">Create an account</a>
                </span>
            </div>
        </div>

        <!-- Security Badge -->
        <div class="mt-6 text-center">
            <div class="inline-flex items-center text-xs text-gray-500">
                <i class="fas fa-lock mr-1"></i>
                <span>This site is protected by reCAPTCHA</span>
                <a href="#" class="ml-1 text-blue-600 hover:underline">Privacy</a>
                <span class="mx-1">·</span>
                <a href="#" class="ml-1 text-blue-600 hover:underline">Terms</a>
            </div>
        </div>
    </div>

    <!-- Registration Modal -->
    <div id="registerModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-xl p-8 max-w-md w-full mx-4 fade-in">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-900">Create an Account</h2>
                <button id="closeRegisterModal" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <!-- Registration Error/Success Messages -->
            <div id="regErrorMessage" class="hidden mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg fade-in">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <span id="regErrorText">Registration failed</span>
                </div>
            </div>
            
            <div id="regSuccessMessage" class="hidden mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg fade-in">
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    <span>Registration successful! Redirecting to login...</span>
                </div>
            </div>

            <form id="registerForm" class="space-y-5">
                <!-- Username Field -->
                <div>
                    <label for="regUsername" class="block text-sm font-medium text-gray-700 mb-2">
                        Username
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-user text-gray-400"></i>
                        </div>
                        <input type="text" 
                               id="regUsername" 
                               name="username" 
                               required
                               class="input-focus w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Choose a username">
                    </div>
                    <span class="text-xs text-red-500 mt-1 hidden" id="regUsernameError">Username is required</span>
                </div>

                <!-- Email Field -->
                <div>
                    <label for="regEmail" class="block text-sm font-medium text-gray-700 mb-2">
                        Email address
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-envelope text-gray-400"></i>
                        </div>
                        <input type="email" 
                               id="regEmail" 
                               name="email" 
                               required
                               class="input-focus w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Enter your email">
                    </div>
                    <span class="text-xs text-red-500 mt-1 hidden" id="regEmailError">Email is required</span>
                </div>

                <!-- Password Field -->
                <div>
                    <label for="regPassword" class="block text-sm font-medium text-gray-700 mb-2">
                        Password
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-lock text-gray-400"></i>
                        </div>
                        <input type="password" 
                               id="regPassword" 
                               name="password" 
                               required
                               class="input-focus w-full pl-10 pr-10 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Create a password">
                        <button type="button" 
                                id="toggleRegPassword"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <i class="fas fa-eye text-gray-400 hover:text-gray-600" id="regEyeIcon"></i>
                        </button>
                    </div>
                    <div class="password-strength mt-2 hidden" id="regPasswordStrength"></div>
                    <span class="text-xs text-red-500 mt-1 hidden" id="regPasswordError">Password is required</span>
                </div>

                <!-- Confirm Password Field -->
                <div>
                    <label for="regConfirmPassword" class="block text-sm font-medium text-gray-700 mb-2">
                        Confirm Password
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-lock text-gray-400"></i>
                        </div>
                        <input type="password" 
                               id="regConfirmPassword" 
                               name="confirmPassword" 
                               required
                               class="input-focus w-full pl-10 pr-10 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Confirm your password">
                        <button type="button" 
                                id="toggleRegConfirmPassword"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <i class="fas fa-eye text-gray-400 hover:text-gray-600" id="regConfirmEyeIcon"></i>
                        </button>
                    </div>
                    <span class="text-xs text-red-500 mt-1 hidden" id="regConfirmPasswordError">Passwords do not match</span>
                </div>

                <!-- Terms and Conditions -->
                <div class="flex items-center">
                    <input type="checkbox" 
                           id="terms" 
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                           required>
                    <span class="ml-2 text-sm text-gray-600">
                        I agree to the <a href="#" class="text-blue-600 hover:underline">Terms and Conditions</a>
                    </span>
                </div>

                <!-- Submit Button -->
                <button type="submit" 
                        id="regSubmitBtn"
                        class="w-full bg-blue-600 text-white font-semibold py-2.5 px-4 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all transform hover:scale-[1.02] flex items-center justify-center">
                    <span id="regBtnText">Create Account</span>
                    <div id="regBtnLoader" class="loading-spinner ml-2 hidden"></div>
                </button>
            </form>
        </div>
    </div>

    <!-- OTP Modal (Hidden by default) -->
    <div id="otpModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-xl p-8 max-w-sm w-full mx-4 fade-in">
            <h3 class="text-xl font-bold text-gray-900 mb-4">Two-Factor Authentication</h3>
            <p class="text-gray-600 mb-6">Enter the 6-digit code from your authenticator app</p>
            
            <div class="flex justify-center space-x-2 mb-6">
                <input type="text" maxlength="1" class="w-12 h-12 text-center border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none">
                <input type="text" maxlength="1" class="w-12 h-12 text-center border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none">
                <input type="text" maxlength="1" class="w-12 h-12 text-center border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none">
                <input type="text" maxlength="1" class="w-12 h-12 text-center border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none">
                <input type="text" maxlength="1" class="w-12 h-12 text-center border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none">
                <input type="text" maxlength="1" class="w-12 h-12 text-center border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none">
            </div>
            
            <div class="flex space-x-3">
                <button onclick="closeOTPModal()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                    Cancel
                </button>
                <button class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    Verify
                </button>
            </div>
        </div>
    </div>

    <script>
        // Password visibility toggle for login form
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eyeIcon');

        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            eyeIcon.classList.toggle('fa-eye');
            eyeIcon.classList.toggle('fa-eye-slash');
        });

        // Password visibility toggle for registration form
        const toggleRegPassword = document.getElementById('toggleRegPassword');
        const regPasswordInput = document.getElementById('regPassword');
        const regEyeIcon = document.getElementById('regEyeIcon');

        toggleRegPassword.addEventListener('click', function() {
            const type = regPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            regPasswordInput.setAttribute('type', type);
            regEyeIcon.classList.toggle('fa-eye');
            regEyeIcon.classList.toggle('fa-eye-slash');
        });

        // Confirm password visibility toggle
        const toggleRegConfirmPassword = document.getElementById('toggleRegConfirmPassword');
        const regConfirmPasswordInput = document.getElementById('regConfirmPassword');
        const regConfirmEyeIcon = document.getElementById('regConfirmEyeIcon');

        toggleRegConfirmPassword.addEventListener('click', function() {
            const type = regConfirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            regConfirmPasswordInput.setAttribute('type', type);
            regConfirmEyeIcon.classList.toggle('fa-eye');
            regConfirmEyeIcon.classList.toggle('fa-eye-slash');
        });

        // Registration modal controls
        const registerModal = document.getElementById('registerModal');
        const openRegisterModal = document.getElementById('openRegisterModal');
        const closeRegisterModal = document.getElementById('closeRegisterModal');

        openRegisterModal.addEventListener('click', function(e) {
            e.preventDefault();
            registerModal.classList.remove('hidden');
            registerModal.classList.add('flex');
        });

        closeRegisterModal.addEventListener('click', function() {
            registerModal.classList.add('hidden');
            registerModal.classList.remove('flex');
            resetRegisterForm();
        });

        // Close modal when clicking outside
        registerModal.addEventListener('click', function(e) {
            if (e.target === registerModal) {
                registerModal.classList.add('hidden');
                registerModal.classList.remove('flex');
                resetRegisterForm();
            }
        });

        // Google Sign-In Handler
        function handleGoogleSignIn(response) {
            console.log('Google Sign-In response received:', response);
            
            // Decode the JWT token to get user info
            const responsePayload = decodeJWTResponse(response.credential);
            console.log('Google user info:', responsePayload);
            
            const googleData = {
                id: responsePayload.sub,
                email: responsePayload.email,
                name: responsePayload.name,
                firstName: responsePayload.given_name,
                lastName: responsePayload.family_name,
                imageUrl: responsePayload.picture,
                idToken: response.credential
            };
            
            // Show loading state
            showLoadingState();
            
            // Send Google data to backend
            fetch('backend/google_auth.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(googleData)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                console.log('Backend response:', data);
                hideLoadingState();
                
                if (data.success) {
                    showSuccess('Google Sign-In successful!');
                    
                    // Show OTP modal if 2FA is enabled
                    if (data.twoFactorRequired) {
                        setTimeout(() => {
                            showOTPModal();
                        }, 1000);
                    } else {
                        // Redirect to dashboard
                        setTimeout(() => {
                            window.location.href = data.redirect || 'dashboard.php';
                        }, 1500);
                    }
                } else {
                    showError('errorMessage', data.message || 'Google authentication failed');
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                hideLoadingState();
                showError('errorMessage', 'An error occurred during Google authentication: ' + error.message);
            });
        }

        function decodeJWTResponse(token) {
            try {
                const base64Url = token.split('.')[1];
                const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
                const jsonPayload = decodeURIComponent(atob(base64).split('').map(function(c) {
                    return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
                }).join(''));
                
                return JSON.parse(jsonPayload);
            } catch (error) {
                console.error('Error decoding JWT:', error);
                return {};
            }
        }

        function showLoadingState() {
            // Disable all social buttons
            const socialButtons = document.querySelectorAll('.social-button, .g_id_signin');
            socialButtons.forEach(btn => {
                btn.style.opacity = '0.6';
                btn.style.pointerEvents = 'none';
            });
            
            // Show loading message
            showSuccess('Authenticating with Google...');
        }

        function hideLoadingState() {
            // Re-enable all social buttons
            const socialButtons = document.querySelectorAll('.social-button, .g_id_signin');
            socialButtons.forEach(btn => {
                btn.style.opacity = '1';
                btn.style.pointerEvents = 'auto';
            });
        }

        // Form validation and submission for login
        const loginForm = document.getElementById('loginForm');
        const submitBtn = document.getElementById('submitBtn');
        const btnText = document.getElementById('btnText');
        const btnLoader = document.getElementById('btnLoader');
        const errorMessage = document.getElementById('errorMessage');
        const successMessage = document.getElementById('successMessage');
        const otpModal = document.getElementById('otpModal');

        loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Reset error states
            hideMessages();
            clearErrors();
            
            // Get form values
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            
            // Validate form
            let isValid = true;
            
            if (!email) {
                showError('emailError');
                isValid = false;
            }
            
            if (!password) {
                showError('passwordError');
                isValid = false;
            }
            
            if (!isValid) {
                shakeForm();
                return;
            }
            
            // Show loading state
            setLoading(true);
            
            // Send data to backend
            try {
                const response = await fetch('backend/login_back.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        email: email,
                        password: password,
                        remember: document.getElementById('remember').checked
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    setLoading(false);
                    showSuccess();
                    
                    // Show OTP modal if 2FA is enabled
                    if (data.twoFactorRequired) {
                        setTimeout(() => {
                            showOTPModal();
                        }, 1000);
                    } else {
                        // Redirect to dashboard
                        setTimeout(() => {
                            window.location.href = data.redirect || 'dashboard.php';
                        }, 1500);
                    }
                } else {
                    setLoading(false);
                    showError('errorMessage', data.message || 'Invalid email or password');
                    shakeForm();
                }
            } catch (error) {
                setLoading(false);
                showError('errorMessage', 'An error occurred. Please try again.');
                shakeForm();
                console.error('Login error:', error);
            }
        });

        // Form validation and submission for registration
        const registerForm = document.getElementById('registerForm');
        const regSubmitBtn = document.getElementById('regSubmitBtn');
        const regBtnText = document.getElementById('regBtnText');
        const regBtnLoader = document.getElementById('regBtnLoader');
        const regErrorMessage = document.getElementById('regErrorMessage');
        const regSuccessMessage = document.getElementById('regSuccessMessage');

        registerForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Reset error states
            hideRegMessages();
            clearRegErrors();
            
            // Get form values
            const username = document.getElementById('regUsername').value.trim();
            const email = document.getElementById('regEmail').value.trim();
            const password = document.getElementById('regPassword').value;
            const confirmPassword = document.getElementById('regConfirmPassword').value;
            const termsAccepted = document.getElementById('terms').checked;
            
            // Validate form
            let isValid = true;
            
            if (!username) {
                showRegError('regUsernameError');
                isValid = false;
            }
            
            if (!email) {
                showRegError('regEmailError');
                isValid = false;
            }
            
            if (!password) {
                showRegError('regPasswordError');
                isValid = false;
            }
            
            if (password !== confirmPassword) {
                showRegError('regConfirmPasswordError');
                isValid = false;
            }
            
            if (!termsAccepted) {
                showRegError('regErrorMessage', 'You must accept the terms and conditions');
                isValid = false;
            }
            
            if (!isValid) {
                shakeRegisterForm();
                return;
            }
            
            // Show loading state
            setRegLoading(true);
            
            // Send data to backend
            try {
                const response = await fetch('backend/register_back.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        username: username,
                        email: email,
                        password: password
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    setRegLoading(false);
                    showRegSuccess();
                    
                    // Close modal and reset form after delay
                    setTimeout(() => {
                        registerModal.classList.add('hidden');
                        registerModal.classList.remove('flex');
                        resetRegisterForm();
                        
                        // Show success message on login form
                        showSuccess('Account created successfully! Please log in.');
                    }, 2000);
                } else {
                    setRegLoading(false);
                    showRegError('regErrorMessage', data.message || 'Registration failed');
                    shakeRegisterForm();
                }
            } catch (error) {
                setRegLoading(false);
                showRegError('regErrorMessage', 'An error occurred. Please try again.');
                shakeRegisterForm();
                console.error('Registration error:', error);
            }
        });

        // Password strength indicator for registration form
        regPasswordInput.addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('regPasswordStrength');
            
            if (password.length > 0) {
                strengthBar.classList.remove('hidden');
                let strength = 0;
                
                if (password.length >= 8) strength++;
                if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
                if (password.match(/[0-9]/)) strength++;
                if (password.match(/[^a-zA-Z0-9]/)) strength++;
                
                strengthBar.className = 'password-strength mt-2';
                
                if (strength === 1) {
                    strengthBar.classList.add('bg-red-500', 'w-1/4');
                } else if (strength === 2) {
                    strengthBar.classList.add('bg-yellow-500', 'w-2/4');
                } else if (strength === 3) {
                    strengthBar.classList.add('bg-blue-500', 'w-3/4');
                } else if (strength === 4) {
                    strengthBar.classList.add('bg-green-500', 'w-full');
                }
            } else {
                strengthBar.classList.add('hidden');
            }
        });

        // Helper functions for login form
        function setLoading(isLoading) {
            if (isLoading) {
                submitBtn.disabled = true;
                btnText.textContent = 'Signing in...';
                btnLoader.classList.remove('hidden');
            } else {
                submitBtn.disabled = false;
                btnText.textContent = 'Sign in';
                btnLoader.classList.add('hidden');
            }
        }

        function showError(errorId, message = null) {
            const errorElement = document.getElementById(errorId);
            errorElement.classList.remove('hidden');
            
            if (message && errorId === 'errorMessage') {
                document.getElementById('errorText').textContent = message;
            }
        }

        function showSuccess(message = null) {
            successMessage.classList.remove('hidden');
            if (message) {
                document.getElementById('successText').textContent = message;
            }
        }

        function hideMessages() {
            errorMessage.classList.add('hidden');
            successMessage.classList.add('hidden');
        }

        function clearErrors() {
            document.getElementById('emailError').classList.add('hidden');
            document.getElementById('passwordError').classList.add('hidden');
        }

        function shakeForm() {
            loginForm.classList.add('shake');
            setTimeout(() => {
                loginForm.classList.remove('shake');
            }, 500);
        }

        // Helper functions for registration form
        function setRegLoading(isLoading) {
            if (isLoading) {
                regSubmitBtn.disabled = true;
                regBtnText.textContent = 'Creating Account...';
                regBtnLoader.classList.remove('hidden');
            } else {
                regSubmitBtn.disabled = false;
                regBtnText.textContent = 'Create Account';
                regBtnLoader.classList.add('hidden');
            }
        }

        function showRegError(errorId, message = null) {
            const errorElement = document.getElementById(errorId);
            errorElement.classList.remove('hidden');
            
            if (message && errorId === 'regErrorMessage') {
                document.getElementById('regErrorText').textContent = message;
            }
        }

        function showRegSuccess() {
            regSuccessMessage.classList.remove('hidden');
        }

        function hideRegMessages() {
            regErrorMessage.classList.add('hidden');
            regSuccessMessage.classList.add('hidden');
        }

        function clearRegErrors() {
            document.getElementById('regUsernameError').classList.add('hidden');
            document.getElementById('regEmailError').classList.add('hidden');
            document.getElementById('regPasswordError').classList.add('hidden');
            document.getElementById('regConfirmPasswordError').classList.add('hidden');
        }

        function shakeRegisterForm() {
            registerForm.classList.add('shake');
            setTimeout(() => {
                registerForm.classList.remove('shake');
            }, 500);
        }

        function resetRegisterForm() {
            registerForm.reset();
            document.getElementById('regPasswordStrength').classList.add('hidden');
            hideRegMessages();
            clearRegErrors();
        }

        function showOTPModal() {
            otpModal.classList.remove('hidden');
            otpModal.classList.add('flex');
        }

        function closeOTPModal() {
            otpModal.classList.add('hidden');
            otpModal.classList.remove('flex');
        }

        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Page loaded successfully');
        });
    </script>
</body>
</html>