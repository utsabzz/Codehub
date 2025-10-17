<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.html');
    exit;
}

// Include database connection
require_once 'db_connection.php';

// Get profile username from URL or use current user
$profile_username = isset($_GET['username']) ? $_GET['username'] : null;
$current_user_id = $_SESSION['user_id'];

// Get current user info
$current_user_sql = "SELECT * FROM users WHERE id = ?";
$current_user_stmt = $conn->prepare($current_user_sql);
$current_user_stmt->bind_param("i", $current_user_id);
$current_user_stmt->execute();
$current_user_result = $current_user_stmt->get_result();
$current_user = $current_user_result->fetch_assoc();

// Get profile user info
if ($profile_username) {
    $profile_sql = "SELECT * FROM users WHERE username = ?";
    $profile_stmt = $conn->prepare($profile_sql);
    $profile_stmt->bind_param("s", $profile_username);
    $profile_stmt->execute();
    $profile_result = $profile_stmt->get_result();
    $profile_user = $profile_result->fetch_assoc();
} else {
    $profile_user = $current_user;
    $profile_username = $current_user['username'];
}

if (!$profile_user) {
    header('Location: dashboard.php');
    exit;
}

$is_own_profile = ($profile_user['id'] == $current_user_id);

// Check if current user is following profile user
$is_following = false;
if (!$is_own_profile) {
    $follow_check_sql = "SELECT id FROM user_following WHERE follower_id = ? AND following_id = ?";
    $follow_check_stmt = $conn->prepare($follow_check_sql);
    $follow_check_stmt->bind_param("ii", $current_user_id, $profile_user['id']);
    $follow_check_stmt->execute();
    $follow_check_result = $follow_check_stmt->get_result();
    $is_following = $follow_check_result->num_rows > 0;
}

// Handle follow/unfollow action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['follow_action'])) {
    if (!$is_own_profile) {
        if ($_POST['follow_action'] === 'follow' && !$is_following) {
            // Follow user
            $follow_sql = "INSERT INTO user_following (follower_id, following_id) VALUES (?, ?)";
            $follow_stmt = $conn->prepare($follow_sql);
            $follow_stmt->bind_param("ii", $current_user_id, $profile_user['id']);
            $follow_stmt->execute();
            $is_following = true;
        } elseif ($_POST['follow_action'] === 'unfollow' && $is_following) {
            // Unfollow user
            $unfollow_sql = "DELETE FROM user_following WHERE follower_id = ? AND following_id = ?";
            $unfollow_stmt = $conn->prepare($unfollow_sql);
            $unfollow_stmt->bind_param("ii", $current_user_id, $profile_user['id']);
            $unfollow_stmt->execute();
            $is_following = false;
        }
    }
}

// Get user stats
// Followers count
$followers_sql = "SELECT COUNT(*) as count FROM user_following WHERE following_id = ?";
$followers_stmt = $conn->prepare($followers_sql);
$followers_stmt->bind_param("i", $profile_user['id']);
$followers_stmt->execute();
$followers_result = $followers_stmt->get_result();
$followers_count = $followers_result->fetch_assoc()['count'];

// Following count
$following_sql = "SELECT COUNT(*) as count FROM user_following WHERE follower_id = ?";
$following_stmt = $conn->prepare($following_sql);
$following_stmt->bind_param("i", $profile_user['id']);
$following_stmt->execute();
$following_result = $following_stmt->get_result();
$following_count = $following_result->fetch_assoc()['count'];

// Repositories count (only public or owned by current user)
if ($is_own_profile) {
    $repos_sql = "SELECT COUNT(*) as count FROM repositories WHERE owner_id = ?";
    $repos_stmt = $conn->prepare($repos_sql);
    $repos_stmt->bind_param("i", $profile_user['id']);
} else {
    $repos_sql = "SELECT COUNT(*) as count FROM repositories WHERE owner_id = ? AND visibility = 'public'";
    $repos_stmt = $conn->prepare($repos_sql);
    $repos_stmt->bind_param("i", $profile_user['id']);
}
$repos_stmt->execute();
$repos_result = $repos_stmt->get_result();
$repos_count = $repos_result->fetch_assoc()['count'];

// Stars count
$stars_sql = "SELECT COUNT(*) as count FROM user_stars WHERE user_id = ?";
$stars_stmt = $conn->prepare($stars_sql);
$stars_stmt->bind_param("i", $profile_user['id']);
$stars_stmt->execute();
$stars_result = $stars_stmt->get_result();
$stars_count = $stars_result->fetch_assoc()['count'];

// Get user repositories (only public or owned by current user)
if ($is_own_profile) {
    $user_repos_sql = "SELECT * FROM repositories WHERE owner_id = ? ORDER BY created_at DESC";
    $user_repos_stmt = $conn->prepare($user_repos_sql);
    $user_repos_stmt->bind_param("i", $profile_user['id']);
} else {
    $user_repos_sql = "SELECT * FROM repositories WHERE owner_id = ? AND visibility = 'public' ORDER BY created_at DESC";
    $user_repos_stmt = $conn->prepare($user_repos_sql);
    $user_repos_stmt->bind_param("i", $profile_user['id']);
}
$user_repos_stmt->execute();
$user_repos_result = $user_repos_stmt->get_result();
$repositories = $user_repos_result->fetch_all(MYSQLI_ASSOC);

// Get user's top languages
$languages_sql = "SELECT 
    CASE 
        WHEN r.language IS NOT NULL THEN r.language
        ELSE 'Other'
    END as language,
    COUNT(*) as count
FROM repositories r 
WHERE r.owner_id = ? 
    AND (r.visibility = 'public' OR ? = 1)
GROUP BY language 
ORDER BY count DESC 
LIMIT 5";
$languages_stmt = $conn->prepare($languages_sql);
$is_owner_int = $is_own_profile ? 1 : 0;
$languages_stmt->bind_param("ii", $profile_user['id'], $is_owner_int);
$languages_stmt->execute();
$languages_result = $languages_stmt->get_result();
$languages = $languages_result->fetch_all(MYSQLI_ASSOC);

// Calculate language percentages
$total_repos = array_sum(array_column($languages, 'count'));
$language_percentages = [];
foreach ($languages as $language) {
    $language_percentages[$language['language']] = round(($language['count'] / $total_repos) * 100, 1);
}

// Get followers list for modal
$followers_list_sql = "SELECT u.id, u.username, u.profile_image 
                      FROM user_following uf 
                      JOIN users u ON uf.follower_id = u.id 
                      WHERE uf.following_id = ? 
                      ORDER BY uf.created_at DESC 
                      LIMIT 50";
$followers_list_stmt = $conn->prepare($followers_list_sql);
$followers_list_stmt->bind_param("i", $profile_user['id']);
$followers_list_stmt->execute();
$followers_list_result = $followers_list_stmt->get_result();
$followers_list = $followers_list_result->fetch_all(MYSQLI_ASSOC);

// Get following list for modal
$following_list_sql = "SELECT u.id, u.username, u.profile_image 
                      FROM user_following uf 
                      JOIN users u ON uf.following_id = u.id 
                      WHERE uf.follower_id = ? 
                      ORDER BY uf.created_at DESC 
                      LIMIT 50";
$following_list_stmt = $conn->prepare($following_list_sql);
$following_list_stmt->bind_param("i", $profile_user['id']);
$following_list_stmt->execute();
$following_list_result = $following_list_stmt->get_result();
$following_list = $following_list_result->fetch_all(MYSQLI_ASSOC);

// Generate contribution data based on repository activity
$contribution_data = [];
$current_year = date('Y');
$start_date = date('Y-m-d', strtotime('-1 year'));

// Get repository activity for contribution graph
$activity_sql = "SELECT 
    DATE(created_at) as date,
    COUNT(*) as count
FROM repositories 
WHERE owner_id = ? 
    AND created_at >= ?
GROUP BY DATE(created_at)
ORDER BY date ASC";
$activity_stmt = $conn->prepare($activity_sql);
$activity_stmt->bind_param("is", $profile_user['id'], $start_date);
$activity_stmt->execute();
$activity_result = $activity_stmt->get_result();

while ($activity = $activity_result->fetch_assoc()) {
    $contribution_data[$activity['date']] = $activity['count'];
}

// Close connection
$conn->close();

// Function to generate contribution grid
function generateContributionGrid($contribution_data) {
    $grid = '';
    $start_date = new DateTime('-1 year');
    $end_date = new DateTime();
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start_date, $interval, $end_date);
    
    foreach ($period as $date) {
        $date_str = $date->format('Y-m-d');
        $count = isset($contribution_data[$date_str]) ? $contribution_data[$date_str] : 0;
        
        // Determine color based on contribution count
        if ($count == 0) {
            $color = 'bg-gray-100';
        } elseif ($count <= 2) {
            $color = 'bg-green-100';
        } elseif ($count <= 5) {
            $color = 'bg-green-300';
        } elseif ($count <= 8) {
            $color = 'bg-green-500';
        } else {
            $color = 'bg-green-700';
        }
        
        $grid .= "<div class='contribution-day w-2.5 h-2.5 $color rounded-sm' title='$count contribution" . ($count != 1 ? 's' : '') . " on " . $date->format('M j, Y') . "'></div>";
    }
    
    return $grid;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($profile_user['username']); ?> - CodeHub</title>
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
            cursor: pointer;
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
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 12px;
            width: 400px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
        
        .modal-header {
            padding: 16px 20px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .user-item {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .user-item:last-child {
            border-bottom: none;
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
                        <i class="fas fa-code-branch text-orange-500 text-2xl"></i>
                        <span class="text-xl font-bold ml-2">CodeHub</span>
                    </div>
                    
                    <div class="hidden md:block">
                        <div class="relative">
                            <input type="text" 
                                   placeholder="Search or jump to..." 
                                   class="w-96 px-4 py-2 pl-10 pr-4 text-sm bg-gray-100 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 focus:bg-white transition-all">
                            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                        </div>
                    </div>
                </div>
                
                <!-- Right Side -->
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="text-gray-600 hover:text-gray-900">
                        <i class="fas fa-th-large text-lg"></i>
                    </a>
                    <a href="projects.php" class="text-gray-600 hover:text-gray-900">
                        <i class="fas fa-book text-lg"></i>
                    </a>
                    <button class="text-gray-600 hover:text-gray-900">
                        <i class="fas fa-bell text-lg"></i>
                    </button>
                    <div class="dropdown relative">
                        <button class="flex items-center space-x-2">
                            <img src="<?php echo !empty($current_user['profile_image']) ? $current_user['profile_image'] : 'https://picsum.photos/seed/user' . $current_user['id'] . '/32/32.jpg'; ?>" 
                                 alt="Profile" 
                                 class="h-8 w-8 rounded-full">
                            <i class="fas fa-chevron-down text-xs text-gray-600"></i>
                        </button>
                    </div>
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
                    <img src="<?php echo !empty($profile_user['profile_image']) ? $profile_user['profile_image'] : 'https://picsum.photos/seed/' . $profile_user['username'] . '/150/150.jpg'; ?>" 
                         alt="Profile" 
                         class="h-32 w-32 rounded-full border-4 border-white shadow-lg">
                    
                    <div class="flex-1 text-center sm:text-left">
                        <h1 class="text-3xl font-bold text-gray-900">
                            <?php echo htmlspecialchars($profile_user['first_name'] . ' ' . $profile_user['last_name']); ?>
                        </h1>
                        <p class="text-gray-600">@<?php echo htmlspecialchars($profile_user['username']); ?></p>
                    </div>
                    
                    <div class="flex space-x-3">
                        <?php if (!$is_own_profile): ?>
                            <form method="POST" action="" class="m-0">
                                <?php if ($is_following): ?>
                                    <input type="hidden" name="follow_action" value="unfollow">
                                    <button type="submit" class="follow-btn px-6 py-2 bg-gray-200 text-gray-900 font-medium rounded-lg hover:bg-gray-300">
                                        Following
                                    </button>
                                <?php else: ?>
                                    <input type="hidden" name="follow_action" value="follow">
                                    <button type="submit" class="follow-btn px-6 py-2 bg-orange-500 text-white font-medium rounded-lg hover:bg-orange-600">
                                        Follow
                                    </button>
                                <?php endif; ?>
                            </form>
                        <?php else: ?>
                            <a href="edit_profile.php" class="follow-btn px-6 py-2 bg-gray-900 text-white font-medium rounded-lg hover:bg-gray-800">
                                Edit Profile
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Bio and Stats -->
                <div class="mt-6">
                    <p class="text-gray-700 mb-4">
                        🚀 Full-stack developer | Building amazing things with code 🌟
                        <br>Open source enthusiast | Tech blogger | Coffee addict ☕
                    </p>
                    
                    <div class="flex flex-wrap gap-4 text-sm text-gray-600">
                        <span><i class="fas fa-map-marker-alt mr-1"></i> San Francisco, CA</span>
                        <span><i class="fas fa-calendar mr-1"></i> Joined <?php echo date('F Y', strtotime($profile_user['created_at'])); ?></span>
                        <span><i class="fas fa-clock mr-1"></i> Last active <?php echo $profile_user['last_login'] ? date('M j, Y', strtotime($profile_user['last_login'])) : 'Never'; ?></span>
                    </div>
                    
                    <!-- Stats -->
                    <div class="flex flex-wrap gap-6 mt-6">
                        <div class="stat-card bg-blue-50 px-4 py-2 rounded-lg" onclick="showFollowersModal()">
                            <span class="font-bold text-blue-600"><?php echo $followers_count; ?></span>
                            <span class="text-gray-600 ml-1">Followers</span>
                        </div>
                        <div class="stat-card bg-green-50 px-4 py-2 rounded-lg" onclick="showFollowingModal()">
                            <span class="font-bold text-green-600"><?php echo $following_count; ?></span>
                            <span class="text-gray-600 ml-1">Following</span>
                        </div>
                        <div class="stat-card bg-purple-50 px-4 py-2 rounded-lg">
                            <span class="font-bold text-purple-600"><?php echo $repos_count; ?></span>
                            <span class="text-gray-600 ml-1">Repositories</span>
                        </div>
                        <div class="stat-card bg-orange-50 px-4 py-2 rounded-lg">
                            <span class="font-bold text-orange-600"><?php echo $stars_count; ?></span>
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
                    Repositories <span class="ml-1 text-gray-400"><?php echo $repos_count; ?></span>
                </button>
                <button class="py-4 px-1 text-sm font-medium text-gray-500 hover:text-gray-700">
                    Projects <span class="ml-1 text-gray-400">3</span>
                </button>
                <button class="py-4 px-1 text-sm font-medium text-gray-500 hover:text-gray-700">
                    Stars <span class="ml-1 text-gray-400"><?php echo $stars_count; ?></span>
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
                        <h2 class="text-xl font-semibold text-gray-900">Pinned Repositories</h2>
                        <?php if ($is_own_profile): ?>
                            <button class="text-sm text-gray-600 hover:text-gray-900">
                                Customize your pins <i class="fas fa-pencil-alt ml-1"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (empty($repositories)): ?>
                        <div class="bg-white rounded-lg border border-gray-200 p-8 text-center">
                            <i class="fas fa-folder-open text-4xl text-gray-300 mb-4"></i>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">No repositories yet</h3>
                            <p class="text-gray-600">
                                <?php if ($is_own_profile): ?>
                                    <a href="create_repository.php" class="text-blue-600 hover:underline">Create your first repository</a> to get started.
                                <?php else: ?>
                                    This user doesn't have any public repositories yet.
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php foreach (array_slice($repositories, 0, 4) as $repo): ?>
                                <div class="pinned-repo bg-white rounded-lg border border-gray-200 p-4 cursor-pointer" onclick="window.location.href='view_repo.php?id=<?php echo $repo['id']; ?>'">
                                    <div class="flex items-center justify-between mb-3">
                                        <div class="flex items-center space-x-2">
                                            <i class="fas fa-book text-gray-400"></i>
                                            <a href="view_repo.php?id=<?php echo $repo['id']; ?>" class="repo-text font-semibold text-blue-600 hover:underline">
                                                <?php echo htmlspecialchars($repo['name']); ?>
                                            </a>
                                            <?php if ($repo['visibility'] === 'private'): ?>
                                                <span class="px-2 py-1 text-xs font-semibold text-gray-600 bg-gray-100 rounded">Private</span>
                                            <?php endif; ?>
                                        </div>
                                        <i class="fas fa-thumbtack text-gray-400"></i>
                                    </div>
                                    <p class="repo-text text-sm text-gray-600 mb-3">
                                        <?php echo htmlspecialchars($repo['description'] ?: 'No description provided'); ?>
                                    </p>
                                    <div class="flex items-center space-x-4 repo-meta text-xs text-gray-500">
                                        <?php if ($repo['language']): ?>
                                            <span class="flex items-center">
                                                <i class="fas fa-circle text-blue-500 mr-1"></i>
                                                <?php echo htmlspecialchars($repo['language']); ?>
                                            </span>
                                        <?php endif; ?>
                                        <span class="flex items-center">
                                            <i class="fas fa-star mr-1"></i>
                                            <?php echo $repo['stars']; ?>
                                        </span>
                                        <span class="flex items-center">
                                            <i class="fas fa-code-branch mr-1"></i>
                                            <?php echo $repo['forks']; ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
                
                <!-- Activity Section -->
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Recent Activity</h2>
                    
                    <div class="bg-white rounded-lg border border-gray-200 p-6 space-y-4">
                        <?php if (empty($repositories)): ?>
                            <p class="text-gray-600 text-center">No recent activity</p>
                        <?php else: ?>
                            <?php foreach (array_slice($repositories, 0, 3) as $repo): ?>
                                <div class="activity-item flex items-start space-x-3">
                                    <div class="flex-shrink-0 w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-code-branch text-blue-600 text-sm"></i>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-sm">
                                            <span class="font-semibold"><?php echo htmlspecialchars($profile_user['username']); ?></span> created repository 
                                            <a href="view_repo.php?id=<?php echo $repo['id']; ?>" class="font-semibold text-blue-600"><?php echo htmlspecialchars($repo['name']); ?></a>
                                        </p>
                                        <p class="text-xs text-gray-500 mt-1"><?php echo date('M j, Y', strtotime($repo['created_at'])); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
            
            <!-- Right Column -->
            <div class="space-y-8">
                <!-- Contribution Graph -->
                <section class="bg-white rounded-lg border border-gray-200 p-4">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4"><?php echo array_sum($contribution_data); ?> contributions in the last year</h3>
                    
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
                        <?php echo generateContributionGrid($contribution_data); ?>
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
                    <?php if (empty($language_percentages)): ?>
                        <p class="text-gray-600 text-sm">No language data available</p>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($language_percentages as $language => $percentage): ?>
                                <div>
                                    <div class="flex justify-between text-sm mb-1">
                                        <span class="text-gray-700"><?php echo htmlspecialchars($language); ?></span>
                                        <span class="text-gray-500"><?php echo $percentage; ?>%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="language-bar bg-blue-500 h-2 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </div>

    <!-- Followers Modal -->
    <div id="followersModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="text-lg font-semibold text-gray-900">Followers</h3>
                <button type="button" onclick="hideFollowersModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <?php if (empty($followers_list)): ?>
                    <p class="text-gray-600 text-center">No followers yet</p>
                <?php else: ?>
                    <div class="space-y-2">
                        <?php foreach ($followers_list as $follower): ?>
                            <div class="user-item">
                                <img src="<?php echo !empty($follower['profile_image']) ? $follower['profile_image'] : 'https://picsum.photos/seed/user' . $follower['id'] . '/32/32.jpg'; ?>" 
                                     alt="<?php echo htmlspecialchars($follower['username']); ?>" 
                                     class="w-8 h-8 rounded-full mr-3">
                                <a href="profile.php?username=<?php echo urlencode($follower['username']); ?>" 
                                   class="font-medium text-gray-900 hover:text-blue-600">
                                    <?php echo htmlspecialchars($follower['username']); ?>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Following Modal -->
    <div id="followingModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="text-lg font-semibold text-gray-900">Following</h3>
                <button type="button" onclick="hideFollowingModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <?php if (empty($following_list)): ?>
                    <p class="text-gray-600 text-center">Not following anyone yet</p>
                <?php else: ?>
                    <div class="space-y-2">
                        <?php foreach ($following_list as $following): ?>
                            <div class="user-item">
                                <img src="<?php echo !empty($following['profile_image']) ? $following['profile_image'] : 'https://picsum.photos/seed/user' . $following['id'] . '/32/32.jpg'; ?>" 
                                     alt="<?php echo htmlspecialchars($following['username']); ?>" 
                                     class="w-8 h-8 rounded-full mr-3">
                                <a href="profile.php?username=<?php echo urlencode($following['username']); ?>" 
                                   class="font-medium text-gray-900 hover:text-blue-600">
                                    <?php echo htmlspecialchars($following['username']); ?>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Modal functions
        function showFollowersModal() {
            document.getElementById('followersModal').style.display = 'block';
        }

        function hideFollowersModal() {
            document.getElementById('followersModal').style.display = 'none';
        }

        function showFollowingModal() {
            document.getElementById('followingModal').style.display = 'block';
        }

        function hideFollowingModal() {
            document.getElementById('followingModal').style.display = 'none';
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = ['followersModal', 'followingModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }

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
    </script>
</body>
</html>