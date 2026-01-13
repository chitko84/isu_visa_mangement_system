<?php
session_start();

// Check if user is logged in and is staff/admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'staff' && $_SESSION['role'] != 'admin')) {
    header("Location: ../login.php");
    exit();
}

// Include database
require_once '../includes/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - ISSU System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background: #f8f9fa;
        }
        .sidebar {
            background: #2c3e50;
            color: white;
            width: 250px;
            height: 100vh;
            position: fixed;
            padding: 20px;
        }
        .sidebar h2 {
            margin-top: 0;
            color: #ecf0f1;
        }
        .sidebar a {
            color: #ecf0f1;
            text-decoration: none;
            display: block;
            padding: 10px;
            margin: 5px 0;
            border-radius: 5px;
        }
        .sidebar a:hover {
            background: #34495e;
        }
        .main-content {
            margin-left: 270px;
            padding: 20px;
        }
        .header {
            background: white;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .card {
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .card h3 {
            margin-top: 0;
            color: #2c3e50;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 5px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>Staff Portal</h2>
        <p>Welcome, <?php echo $_SESSION['full_name']; ?></p>
        <p>Role: <?php echo $_SESSION['role']; ?></p>
        <hr>
        <a href="dashboard.php" style="background: #34495e;">Dashboard</a>
        <a href="students.php">Students</a>
        <a href="visa_management.php">Visa Applications</a>
        <a href="insurance_claims.php">Insurance Claims</a>
        <a href="exit_cases.php">Exit Cases</a>
        <a href="reports.php">Reports</a>
        <?php if ($_SESSION['role'] == 'admin'): ?>
            <a href="settings.php">Admin Settings</a>
        <?php endif; ?>
        <hr>
        <a href="../logout.php">Logout</a>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1>Staff Dashboard</h1>
            <p>Welcome, <?php echo $_SESSION['full_name']; ?> (<?php echo $_SESSION['role']; ?>)</p>
        </div>
        
        <div class="stats">
            <?php
            // Get statistics
            $total_students = $conn->query("SELECT COUNT(*) as total FROM student")->fetch_assoc()['total'];
            $active_visas = $conn->query("SELECT COUNT(*) as total FROM student_visa WHERE status = 'Active'")->fetch_assoc()['total'];
            $pending_apps = $conn->query("SELECT COUNT(*) as total FROM visa_renewal_application WHERE status = 'Pending'")->fetch_assoc()['total'];
            $expiring_visas = $conn->query("SELECT COUNT(*) as total FROM student_visa WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)")->fetch_assoc()['total'];
            ?>
            
            <div class="stat-box">
                <div class="stat-number"><?php echo $total_students; ?></div>
                <div>Total Students</div>
            </div>
            
            <div class="stat-box">
                <div class="stat-number"><?php echo $active_visas; ?></div>
                <div>Active Visas</div>
            </div>
            
            <div class="stat-box">
                <div class="stat-number"><?php echo $pending_apps; ?></div>
                <div>Pending Apps</div>
            </div>
            
            <div class="stat-box">
                <div class="stat-number"><?php echo $expiring_visas; ?></div>
                <div>Expiring Soon</div>
            </div>
        </div>
        
        <div class="cards">
            <div class="card">
                <h3>Quick Actions</h3>
                <p><a href="students.php">View All Students</a></p>
                <p><a href="visa_management.php">Process Visa Applications</a></p>
                <p><a href="reports.php">Generate Reports</a></p>
            </div>
            
            <div class="card">
                <h3>Recent Students</h3>
                <?php
                $query = "SELECT student_id, first_name, last_name, email FROM student ORDER BY student_id DESC LIMIT 5";
                $result = $conn->query($query);
                
                if ($result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
                        echo "<p>" . $row['first_name'] . " " . $row['last_name'] . " (" . $row['email'] . ")</p>";
                    }
                }
                ?>
            </div>
            
            <div class="card">
                <h3>System Status</h3>
                <p>✅ Database: Connected</p>
                <p>✅ PHP: Running</p>
                <p>✅ Session: Active</p>
                <p>👤 User: <?php echo $_SESSION['role']; ?></p>
            </div>
        </div>
    </div>
</body>
</html>
