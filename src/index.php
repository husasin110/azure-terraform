<?php
session_start();

$host = getenv('DB_SERVER') ?: 'sql-entapp-30278.database.windows.net';
$db   = getenv('DB_NAME') ?: 'sqldb-enterprise-app';
$user = getenv('DB_USER') ?: 'sqladmin';
$pass = getenv('DB_PASSWORD') ?: '';

$conn = null;
$message = "";
$error = "";

try {
    // Use native PDO ODBC driver built into Azure Linux App Service
    $dsn = "odbc:Driver={ODBC Driver 18 for SQL Server};Server=$host,1433;Database=$db;Encrypt=yes;TrustServerCertificate=yes;";
    $conn = new PDO($dsn, $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Initialize Database Tables
    $conn->exec("IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='Users' and xtype='U')
                 CREATE TABLE Users (ID int IDENTITY(1,1) PRIMARY KEY, Username varchar(100) UNIQUE, PasswordHash varchar(255), CreatedAt datetime DEFAULT GETDATE())");

    $conn->exec("IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='Contacts' and xtype='U')
                 CREATE TABLE Contacts (ID int IDENTITY(1,1) PRIMARY KEY, Name varchar(100), PhoneNumber varchar(50), CreatedAt datetime DEFAULT GETDATE())");

    // Handle Logout
    if (isset($_GET['logout'])) {
        session_destroy();
        header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
        exit;
    }

    // Handle Form Actions (Register, Login, Add Contact)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        // User Registration
        if ($action === 'register') {
            $u = trim($_POST['username']);
            $p = $_POST['password'];
            if (!empty($u) && !empty($p)) {
                $hash = password_hash($p, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO Users (Username, PasswordHash) VALUES (:u, :p)");
                $stmt->execute(['u' => $u, 'p' => $hash]);
                $message = "Registration successful! You can now log in.";
            } else {
                $error = "All fields are required for registration.";
            }
        }
        
        // User Login
        elseif ($action === 'login') {
            $u = trim($_POST['username']);
            $p = $_POST['password'];
            $stmt = $conn->prepare("SELECT * FROM Users WHERE Username = :u");
            $stmt->execute(['u' => $u]);
            $dbUser = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($dbUser && password_verify($p, $dbUser['PasswordHash'])) {
                $_SESSION['user'] = $dbUser['Username'];
            } else {
                $error = "Invalid username or password.";
            }
        }

        // Add Contact (Protected Action)
        elseif ($action === 'add_contact' && isset($_SESSION['user'])) {
            $name = $_POST['contact_name'];
            $phone = $_POST['contact_number'];
            if (!empty($name) && !empty($phone)) {
                $stmt = $conn->prepare("INSERT INTO Contacts (Name, PhoneNumber) VALUES (:name, :phone)");
                $stmt->execute(['name' => $name, 'phone' => $phone]);
                $message = "Contact successfully stored!";
            }
        }
    }
} catch (Exception $e) {
    $error = "Database Error: " . htmlspecialchars($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Enterprise PaaS - Secure Portal</title>
    <style>
        body { font-family: sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; background: #0f172a; color: #f8fafc; }
        .card { background: #1e293b; padding: 2.5rem; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); width: 400px; box-sizing: border-box; }
        h2 { color: #38bdf8; margin-top: 0; }
        input[type="text"], input[type="password"], input[type="tel"] { width: 100%; padding: 0.75rem; margin: 0.25rem 0 1rem 0; background: #0f172a; border: 1px solid #334155; color: white; border-radius: 6px; box-sizing: border-box; }
        button { background: #38bdf8; color: #0f172a; border: none; padding: 0.75rem; font-weight: bold; border-radius: 6px; cursor: pointer; width: 100%; }
        button:hover { background: #7dd3fc; }
        .msg { background: #1e3a8a; color: #93c5fd; padding: 0.75rem; border-radius: 6px; margin-bottom: 1rem; font-size: 0.875rem; }
        .err { background: #7f1d1d; color: #fca5a5; padding: 0.75rem; border-radius: 6px; margin-bottom: 1rem; font-size: 0.875rem; }
        .toggle-btn { background: transparent; color: #38bdf8; border: none; margin-top: 1rem; cursor: pointer; width: 100%; font-size: 0.85rem; }
        .toggle-btn:hover { text-decoration: underline; background: transparent; }
        ul { padding-left: 20px; color: #94a3b8; max-height: 150px; overflow-y: auto; }
        li { margin-bottom: 0.25rem; font-size: 0.9rem; }
        .logout { float: right; color: #f87171; text-decoration: none; font-size: 0.85rem; }
    </style>
</head>
<body>
    <div class="card">
        <?php if(!empty($message)): ?><div class="msg"><?= $message ?></div><?php endif; ?>
        <?php if(!empty($error)): ?><div class="err"><?= $error ?></div><?php endif; ?>

        <?php if (!isset($_SESSION['user'])): ?>
            <!-- LOGIN / REGISTER FORMS -->
            <div id="login-container">
                <h2>Login Portal</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="login">
                    <label>Username:</label>
                    <input type="text" name="username" required autocomplete="off">
                    <label>Password:</label>
                    <input type="password" name="password" required>
                    <button type="submit">Log In</button>
                </form>
                <button class="toggle-btn" onclick="toggleForms()">Need an account? Register</button>
            </div>

            <div id="register-container" style="display: none;">
                <h2>Register Account</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="register">
                    <label>Username:</label>
                    <input type="text" name="username" required autocomplete="off">
                    <label>Password:</label>
                    <input type="password" name="password" required>
                    <button type="submit">Sign Up</button>
                </form>
                <button class="toggle-btn" onclick="toggleForms()">Already have an account? Log In</button>
            </div>

            <script>
                function toggleForms() {
                    const l = document.getElementById('login-container');
                    const r = document.getElementById('register-container');
                    l.style.display = l.style.display === 'none' ? 'block' : 'none';
                    r.style.display = r.style.display === 'none' ? 'block' : 'none';
                }
            </script>

        <?php else: ?>
            <!-- DASHBOARD AFTER SUCCESSFUL LOGIN -->
            <h2>Welcome, <?= htmlspecialchars($_SESSION['user']) ?> <a href="?logout=true" class="logout">Logout</a></h2>
            
            <form method="POST">
                <input type="hidden" name="action" value="add_contact">
                <label>Name:</label>
                <input type="text" name="contact_name" placeholder="Enter contact name..." required autocomplete="off">
                
                <label>Number:</label>
                <input type="tel" name="contact_number" placeholder="Enter phone number..." required autocomplete="off">
                
                <button type="submit">Save Contact</button>
            </form>
            
            <h3>Saved Contacts:</h3>
            <ul>
                <?php
                if ($conn) {
                    try {
                        $stmt = $conn->query("SELECT Name, PhoneNumber, CreatedAt FROM Contacts ORDER BY CreatedAt DESC");
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            echo "<li><strong>" . htmlspecialchars($row['Name']) . "</strong>: " . htmlspecialchars($row['PhoneNumber']) . "</li>";
                        }
                    } catch (Exception $ex) {
                        echo "<li>Unable to fetch records.</li>";
                    }
                }
                ?>
            </ul>
        <?php endif; ?>
    </div>
</body>
</html>