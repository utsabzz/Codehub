<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>John Doe - GitHub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
        }
        
        .contribution-day {
            transition: all 0.2s ease;
        }
        
        .contribution-day:hover {
            transform: scale(1.2);
            border: 1px solid #333;
        }
        
        .repo-card {
            transition: all 0.3s ease;
        }
        
        .repo-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
        }
        
        .tab-active {
            border-bottom: 2px solid #f97316;
            color: #f97316;
        }
        
        .stat-card {
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.1);
        }
        
        .pinned-repo {
            transition: all 0.3s ease;
        }
        
        .pinned-repo:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .pinned-repo:hover .repo-text {
            color: white;
        }
        
        .pinned-repo:hover .repo-meta {
            color: rgba(255, 255, 255, 0.8);
        }
        
        .achievement-badge {
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        .language-bar {
            transition: width 1s ease-out;
        }
        
        .follow-btn {
            transition: all 0.3s ease;
        }
        
        .follow-btn:hover {
            transform: scale(1.05);
        }
        
        .org-logo {
            transition: all 0.3s ease;
        }
        
        .org-logo:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .activity-item {
            animation: slideIn 0.5s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .profile-cover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .scrollbar-thin::-webkit-scrollbar {
            width: 6px;
        }
        
        .scrollbar-thin::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        .scrollbar-thin::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 3px;
        }
        
        .scrollbar-thin::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white border-b border-gray-200 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <!-- Logo and Search -->
                <div class="flex items-center space-x-8">
                    <div class="flex items-center">
                        <svg class="h-8 w-8 text-gray-900" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                        </svg>
                    </div>
                    
                    <div class="hidden md:block">
                        <div class="relative">
                            <input type="text" 
                                   placeholder="Search or jump to..." 
                                   class="w-96 px-4 py-2 pl-10 pr-4 text-sm bg-gray-100 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 focus:bg-white transition-all">
                            <svg class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
                
                <!-- Right Side -->
                <div class="flex items-center space-x-4">
                    <button class="text-gray-600 hover:text-gray-900">
                        <i class="fas fa-pull-request text-lg"></i>
                    </button>
                    <button class="text-gray-600 hover:text-gray-900">
                        <i class="fas fa-issue-opened text-lg"></i>
                    </button>
                    <button class="text-gray-600 hover:text-gray-900">
                        <i class="fas fa-bell text-lg"></i>
                    </button>
                    <button class="text-gray-600 hover:text-gray-900">
                        <i class="fas fa-plus text-lg"></i>
                    </button>
                    <img src="https://picsum.photos/seed/profile/32/32.jpg" alt="Profile" class="h-8 w-8 rounded-full cursor-pointer">
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Profile Header -->
    <div class="profile-cover h-48"></div>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-20">
        <div class="bg-white rounded-lg shadow-lg">
            <div class="px-8 pb-8">
                <!-- Profile Info -->
                <div class="flex flex-col sm:flex-row items-center sm:items-end space-y-4 sm:space-y-0 sm:space-x-6">
                    <img src="https://picsum.photos/seed/johndoe/150/150.jpg" 
                         alt="Profile" 
                         class="h-32 w-32 rounded-full border-4 border-white shadow-lg">
                    
                    <div class="flex-1 text-center sm:text-left">
                        <h1 class="text-3xl font-bold text-gray-900">John Doe</h1>
                        <p class="text-gray-600">@johndoe</p>
                    </div>
                    
                    <div class="flex space-x-3">
                        <button class="follow-btn px-6 py-2 bg-gray-900 text-white font-medium rounded-lg hover:bg-gray-800">
                            Follow
                        </button>
                        <button class="px-6 py-2 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50">
                            <i class="fas fa-envelope mr-2"></i>Contact
                        </button>
                    </div>
                </div>
                
                <!-- Bio and Stats -->
                <div class="mt-6">
                    <p class="text-gray-700 mb-4">
                        🚀 Full-stack developer | React, Node.js, Python | Building amazing things with code 🌟
                        <br>Open source enthusiast | Tech blogger | Coffee addict ☕
                    </p>
                    
                    <div class="flex flex-wrap gap-4 text-sm text-gray-600">
                        <span><i class="fas fa-map-marker-alt mr-1"></i> San Francisco, CA</span>
                        <span><i class="fas fa-link mr-1"></i> <a href="#" class="text-blue-600 hover:underline">johndoe.dev</a></span>
                        <span><i class="fas fa-twitter mr-1"></i> <a href="#" class="text-blue-600 hover:underline">@johndoe</a></span>
                        <span><i class="fas fa-calendar mr-1"></i> Joined March 2020</span>
                    </div>
                    
                    <!-- Stats -->
                    <div class="flex flex-wrap gap-6 mt-6">
                        <div class="stat-card bg-blue-50 px-4 py-2 rounded-lg">
                            <span class="font-bold text-blue-600">1.2k</span>
                            <span class="text-gray-600 ml-1">Followers</span>
                        </div>
                        <div class="stat-card bg-green-50 px-4 py-2 rounded-lg">
                            <span class="font-bold text-green-600">342</span>
                            <span class="text-gray-600 ml-1">Following</span>
                        </div>
                        <div class="stat-card bg-purple-50 px-4 py-2 rounded-lg">
                            <span class="font-bold text-purple-600">24</span>
                            <span class="text-gray-600 ml-1">Repositories</span>
                        </div>
                        <div class="stat-card bg-orange-50 px-4 py-2 rounded-lg">
                            <span class="font-bold text-orange-600">89</span>
                            <span class="text-gray-600 ml-1">Stars</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-8">
        <!-- Tabs -->
        <div class="border-b border-gray-200 mb-8">
            <nav class="flex space-x-8">
                <button class="tab-active py-4 px-1 text-sm font-medium">Overview</button>
                <button class="py-4 px-1 text-sm font-medium text-gray-500 hover:text-gray-700">
                    Repositories <span class="ml-1 text-gray-400">24</span>
                </button>
                <button class="py-4 px-1 text-sm font-medium text-gray-500 hover:text-gray-700">
                    Projects <span class="ml-1 text-gray-400">3</span>
                </button>
                <button class="py-4 px-1 text-sm font-medium text-gray-500 hover:text-gray-700">
                    Packages <span class="ml-1 text-gray-400">5</span>
                </button>
                <button class="py-4 px-1 text-sm font-medium text-gray-500 hover:text-gray-700">
                    Stars <span class="ml-1 text-gray-400">89</span>
                </button>
            </nav>
        </div>
        
        <!-- Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Column -->
            <div class="lg:col-span-2 space-y-8">
                <!-- Pinned Repositories -->
                <section>
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-semibold text-gray-900">Pinned</h2>
                        <button class="text-sm text-gray-600 hover:text-gray-900">
                            Customize your pins <i class="fas fa-pencil-alt ml-1"></i>
                        </button>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Pinned Repo 1 -->
                        <div class="pinned-repo bg-white rounded-lg border border-gray-200 p-4 cursor-pointer">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center space-x-2">
                                    <i class="fas fa-book text-gray-400"></i>
                                    <a href="#" class="repo-text font-semibold text-blue-600 hover:underline">awesome-project</a>
                                </div>
                                <i class="fas fa-thumbtack text-gray-400"></i>
                            </div>
                            <p class="repo-text text-sm text-gray-600 mb-3">A modern web application built with React and Node.js</p>
                            <div class="flex items-center space-x-4 repo-meta text-xs text-gray-500">
                                <span class="flex items-center">
                                    <i class="fas fa-circle text-yellow-500 mr-1"></i>
                                    JavaScript
                                </span>
                                <span class="flex items-center">
                                    <i class="fas fa-star mr-1"></i>
                                    142
                                </span>
                                <span class="flex items-center">
                                    <i class="fas fa-code-branch mr-1"></i>
                                    28
                                </span>
                            </div>
                        </div>
                        
                        <!-- Pinned Repo 2 -->
                        <div class="pinned-repo bg-white rounded-lg border border-gray-200 p-4 cursor-pointer">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center space-x-2">
                                    <i class="fas fa-book text-gray-400"></i>
                                    <a href="#" class="repo-text font-semibold text-blue-600 hover:underline">react-components</a>
                                </div>
                                <i class="fas fa-thumbtack text-gray-400"></i>
                            </div>
                            <p class="repo-text text-sm text-gray-600 mb-3">Reusable React components with TypeScript support</p>
                            <div class="flex items-center space-x-4 repo-meta text-xs text-gray-500">
                                <span class="flex items-center">
                                    <i class="fas fa-circle text-blue-500 mr-1"></i>
                                    TypeScript
                                </span>
                                <span class="flex items-center">
                                    <i class="fas fa-star mr-1"></i>
                                    89
                                </span>
                                <span class="flex items-center">
                                    <i class="fas fa-code-branch mr-1"></i>
                                    15
                                </span>
                            </div>
                        </div>
                        
                        <!-- Pinned Repo 3 -->
                        <div class="pinned-repo bg-white rounded-lg border border-gray-200 p-4 cursor-pointer">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center space-x-2">
                                    <i class="fas fa-book text-gray-400"></i>
                                    <a href="#" class="repo-text font-semibold text-blue-600 hover:underline">python-ml-toolkit</a>
                                </div>
                                <i class="fas fa-thumbtack text-gray-400"></i>
                            </div>
                            <p class="repo-text text-sm text-gray-600 mb-3">Machine learning utilities and algorithms in Python</p>
                            <div class="flex items-center space-x-4 repo-meta text-xs text-gray-500">
                                <span class="flex items-center">
                                    <i class="fas fa-circle text-green-500 mr-1"></i>
                                    Python
                                </span>
                                <span class="flex items-center">
                                    <i class="fas fa-star mr-1"></i>
                                    234
                                </span>
                                <span class="flex items-center">
                                    <i class="fas fa-code-branch mr-1"></i>
                                    42
                                </span>
                            </div>
                        </div>
                        
                        <!-- Pinned Repo 4 -->
                        <div class="pinned-repo bg-white rounded-lg border border-gray-200 p-4 cursor-pointer">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center space-x-2">
                                    <i class="fas fa-book text-gray-400"></i>
                                    <a href="#" class="repo-text font-semibold text-blue-600 hover:underline">design-system</a>
                                </div>
                                <i class="fas fa-thumbtack text-gray-400"></i>
                            </div>
                            <p class="repo-text text-sm text-gray-600 mb-3">Component library and design tokens for consistent UI</p>
                            <div class="flex items-center space-x-4 repo-meta text-xs text-gray-500">
                                <span class="flex items-center">
                                    <i class="fas fa-circle text-purple-500 mr-1"></i>
                                    CSS
                                </span>
                                <span class="flex items-center">
                                    <i class="fas fa-star mr-1"></i>
                                    67
                                </span>
                                <span class="flex items-center">
                                    <i class="fas fa-code-branch mr-1"></i>
                                    12
                                </span>
                            </div>
                        </div>
                    </div>
                </section>
                
                <!-- Activity Section -->
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Activity</h2>
                    
                    <div class="bg-white rounded-lg border border-gray-200 p-6 space-y-4">
                        <!-- Activity Item 1 -->
                        <div class="activity-item flex items-start space-x-3">
                            <div class="flex-shrink-0 w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-code-branch text-green-600 text-sm"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm">
                                    <span class="font-semibold">john.doe</span> created branch 
                                    <span class="font-mono bg-gray-100 px-1 rounded">feature/authentication</span> in 
                                    <a href="#" class="font-semibold text-blue-600">awesome-project</a>
                                </p>
                                <p class="text-xs text-gray-500 mt-1">2 hours ago</p>
                            </div>
                        </div>
                        
                        <!-- Activity Item 2 -->
                        <div class="activity-item flex items-start space-x-3">
                            <div class="flex-shrink-0 w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-star text-purple-600 text-sm"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm">
                                    <span class="font-semibold">john.doe</span> starred 
                                    <a href="#" class="font-semibold text-blue-600">facebook/react</a>
                                </p>
                                <p class="text-xs text-gray-500 mt-1">5 hours ago</p>
                            </div>
                        </div>
                        
                        <!-- Activity Item 3 -->
                        <div class="activity-item flex items-start space-x-3">
                            <div class="flex-shrink-0 w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-code-commit text-blue-600 text-sm"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm">
                                    <span class="font-semibold">john.doe</span> pushed to 
                                    <span class="font-mono bg-gray-100 px-1 rounded">main</span> at 
                                    <a href="#" class="font-semibold text-blue-600">react-components</a>
                                </p>
                                <div class="mt-2 bg-gray-50 rounded p-2">
                                    <p class="text-xs font-mono text-gray-600">
                                        <span class="text-green-600">+</span> 23 files changed
                                    </p>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">1 day ago</p>
                            </div>
                        </div>
                        
                        <!-- Activity Item 4 -->
                        <div class="activity-item flex items-start space-x-3">
                            <div class="flex-shrink-0 w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-exclamation-circle text-orange-600 text-sm"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm">
                                    <span class="font-semibold">john.doe</span> opened issue 
                                    <a href="#" class="font-semibold text-blue-600">#42</a> in 
                                    <a href="#" class="font-semibold text-blue-600">awesome-project</a>
                                </p>
                                <p class="text-xs text-gray-600 mt-1">Fix responsive layout on mobile devices</p>
                                <p class="text-xs text-gray-500 mt-1">2 days ago</p>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
            
            <!-- Right Column -->
            <div class="space-y-8">
                <!-- Contribution Graph -->
                <section class="bg-white rounded-lg border border-gray-200 p-4">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">1,247 contributions in the last year</h3>
                    
                    <!-- Months -->
                    <div class="flex justify-between text-xs text-gray-500 mb-2">
                        <span>Jan</span>
                        <span>Mar</span>
                        <span>May</span>
                        <span>Jul</span>
                        <span>Sep</span>
                        <span>Nov</span>
                    </div>
                    
                    <!-- Contribution Grid -->
                    <div class="grid grid-cols-52 gap-1 mb-2">
                        <!-- Generate contribution squares -->
                        <script>
                            document.write(Array(364).fill(0).map(() => {
                                const level = Math.floor(Math.random() * 5);
                                const colors = ['bg-gray-100', 'bg-green-100', 'bg-green-300', 'bg-green-500', 'bg-green-700'];
                                return `<div class="contribution-day w-2.5 h-2.5 ${colors[level]} rounded-sm" title="0 contributions"></div>`;
                            }).join(''));
                        </script>
                    </div>
                    
                    <!-- Legend -->
                    <div class="flex items-center justify-between text-xs text-gray-500">
                        <span>Less</span>
                        <div class="flex space-x-1">
                            <div class="w-2.5 h-2.5 bg-gray-100 rounded-sm"></div>
                            <div class="w-2.5 h-2.5 bg-green-100 rounded-sm"></div>
                            <div class="w-2.5 h-2.5 bg-green-300 rounded-sm"></div>
                            <div class="w-2.5 h-2.5 bg-green-500 rounded-sm"></div>
                            <div class="w-2.5 h-2.5 bg-green-700 rounded-sm"></div>
                        </div>
                        <span>More</span>
                    </div>
                </section>
                
                <!-- Organizations -->
                <section>
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Organizations</h3>
                    <div class="flex flex-wrap gap-3">
                        <div class="org-logo w-12 h-12 bg-gray-200 rounded-lg flex items-center justify-center cursor-pointer">
                            <span class="text-xs font-bold">ACME</span>
                        </div>
                        <div class="org-logo w-12 h-12 bg-gray-200 rounded-lg flex items-center justify-center cursor-pointer">
                            <span class="text-xs font-bold">TECH</span>
                        </div>
                        <div class="org-logo w-12 h-12 bg-gray-200 rounded-lg flex items-center justify-center cursor-pointer">
                            <span class="text-xs font-bold">DEV</span>
                        </div>
                        <div class="org-logo w-12 h-12 bg-gray-200 rounded-lg flex items-center justify-center cursor-pointer">
                            <span class="text-xs font-bold">OSS</span>
                        </div>
                    </div>
                </section>
                
                <!-- Achievements -->
                <section>
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Achievements</h3>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="achievement-badge text-center">
                            <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-1">
                                <i class="fas fa-star text-yellow-600 text-xl"></i>
                            </div>
                            <p class="text-xs text-gray-600">Star</p>
                        </div>
                        <div class="achievement-badge text-center" style="animation-delay: 0.5s">
                            <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-1">
                                <i class="fas fa-fire text-blue-600 text-xl"></i>
                            </div>
                            <p class="text-xs text-gray-600">Hot Streak</p>
                        </div>
                        <div class="achievement-badge text-center" style="animation-delay: 1s">
                            <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-1">
                                <i class="fas fa-rocket text-purple-600 text-xl"></i>
                            </div>
                            <p class="text-xs text-gray-600">Quick Learner</p>
                        </div>
                    </div>
                </section>
                
                <!-- Languages -->
                <section>
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Top Languages</h3>
                    <div class="space-y-3">
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-700">JavaScript</span>
                                <span class="text-gray-500">45%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="language-bar bg-yellow-500 h-2 rounded-full" style="width: 45%"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-700">TypeScript</span>
                                <span class="text-gray-500">25%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="language-bar bg-blue-500 h-2 rounded-full" style="width: 25%"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-700">Python</span>
                                <span class="text-gray-500">20%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="language-bar bg-green-500 h-2 rounded-full" style="width: 20%"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-700">CSS</span>
                                <span class="text-gray-500">10%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="language-bar bg-purple-500 h-2 rounded-full" style="width: 10%"></div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="bg-gray-900 text-gray-300 mt-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="text-center text-sm">
                <p>&copy; 2024 GitHub, Inc. Clone for demonstration purposes.</p>
            </div>
        </div>
    </footer>
    
    <script>
        // Follow button functionality
        const followBtn = document.querySelector('.follow-btn');
        let isFollowing = false;
        
        followBtn.addEventListener('click', function() {
            isFollowing = !isFollowing;
            if (isFollowing) {
                this.textContent = 'Following';
                this.classList.remove('bg-gray-900', 'hover:bg-gray-800');
                this.classList.add('bg-gray-200', 'text-gray-900', 'hover:bg-gray-300');
            } else {
                this.textContent = 'Follow';
                this.classList.remove('bg-gray-200', 'text-gray-900', 'hover:bg-gray-300');
                this.classList.add('bg-gray-900', 'hover:bg-gray-800');
            }
        });
        
        // Tab switching
        const tabs = document.querySelectorAll('nav button');
        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                tabs.forEach(t => t.classList.remove('tab-active'));
                this.classList.add('tab-active');
            });
        });
        
        // Animate language bars on scroll
        const observerOptions = {
            threshold: 0.5
        };
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const bars = entry.target.querySelectorAll('.language-bar');
                    bars.forEach(bar => {
                        const width = bar.style.width;
                        bar.style.width = '0';
                        setTimeout(() => {
                            bar.style.width = width;
                        }, 100);
                    });
                }
            });
        }, observerOptions);
        
        const languagesSection = document.querySelector('.space-y-3');
        if (languagesSection) {
            observer.observe(languagesSection);
        }
        
        // Pinned repo hover effect
        const pinnedRepos = document.querySelectorAll('.pinned-repo');
        pinnedRepos.forEach(repo => {
            repo.addEventListener('mouseenter', function() {
                this.querySelector('.fa-thumbtack').classList.add('text-white');
            });
            repo.addEventListener('mouseleave', function() {
                this.querySelector('.fa-thumbtack').classList.remove('text-white');
            });
        });
        
        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
        
        // Add tooltip to contribution days
        const contributionDays = document.querySelectorAll('.contribution-day');
        contributionDays.forEach(day => {
            const contributions = Math.floor(Math.random() * 10);
            day.title = `${contributions} contribution${contributions !== 1 ? 's' : ''}`;
        });
    </script>
</body>
</html>