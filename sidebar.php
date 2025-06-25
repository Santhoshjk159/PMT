<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['email'])) {
    header("Location: paperworklogin.php");
    exit();
}

// Get the current page name for highlighting active menu item
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<aside class="sidebar">
    <!-- Sidebar header -->
    <header class="sidebar-header">
      <a href="#" class="header-logo">
        <img src="logo.png" alt="Logo">
      </a>
      <button class="toggler sidebar-toggler">
        <span class="material-symbols-rounded">chevron_left</span>
      </button>
      <button class="toggler menu-toggler">
        <span class="material-symbols-rounded">menu</span>
      </button>
    </header>

    <nav class="sidebar-nav">
      <!-- Primary top nav -->
      <ul class="nav-list primary-nav">
        <li class="nav-item">
          <a href="index.php" class="nav-link <?php echo ($currentPage == 'dashboard.php') ? 'active' : ''; ?>">
            <span class="nav-icon material-symbols-rounded">dashboard</span>
            <span class="nav-label">Dashboard</span>
          </a>
          <span class="nav-tooltip">Dashboard</span>
        </li>
        <li class="nav-item">
          <a href="paperworkallrecords.php" class="nav-link <?php echo ($currentPage == 'calendar.php') ? 'active' : ''; ?>">
            <span class="nav-icon material-symbols-rounded">calendar_today</span>
            <span class="nav-label">View History</span>
          </a>
          <span class="nav-tooltip">View History</span>
        </li>
        
        <li class="nav-item">
          <a href="usermanagement.php" class="nav-link <?php echo ($currentPage == 'usermanagement.php') ? 'active' : ''; ?>">
            <span class="nav-icon material-symbols-rounded">group</span>
            <span class="nav-label">Users</span>
          </a>
          <span class="nav-tooltip">Users</span>
        </li>
        

        <li class="nav-item">
            <a href="activity_logs.php" class="nav-link <?php echo ($currentPage == 'activity_logs.php') ? 'active' : ''; ?>">
                <span class="icon">
                    <i class="fas fa-history"></i>
                </span>
                <span class="text">Activity Logs</span>
            </a>
        </li>

        <li class="nav-item">
          <a href="dropdown_settings.php" class="nav-link <?php echo ($currentPage == 'dropdown_settings.php') ? 'active' : ''; ?>">
            <span class="nav-icon material-symbols-rounded">settings</span>
            <span class="nav-label">Settings</span>
          </a>
          <span class="nav-tooltip">Settings</span>
        </li>
      </ul>

      <!-- Secondary bottom nav -->
      <ul class="nav-list secondary-nav">
        <li class="nav-item">
          <a href="profile.php" class="nav-link <?php echo ($currentPage == 'profile.php') ? 'active' : ''; ?>">
            <span class="nav-icon material-symbols-rounded">account_circle</span>
            <span class="nav-label">Profile</span>
          </a>
          <span class="nav-tooltip">Profile</span>
        </li>
        <li class="nav-item">
          <a href="logout.php" class="nav-link">
            <span class="nav-icon material-symbols-rounded">logout</span>
            <span class="nav-label">Logout</span>
          </a>
          <span class="nav-tooltip">Logout</span>
        </li>
      </ul>
    </nav>
  </aside>