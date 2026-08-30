<!DOCT<?php
// Azure SQL Database credentials
$host = getenv('DB_SERVER') ?: 'sql-entapp-30278.database.windows.net';
$db   = getenv('DB_NAME') ?: 'sqldb-enterprise-app';
$user = getenv('DB_USER') ?: 'sqladmin';
$pass = getenv('DB_PASSWORD') ?: 'YOUR_SQL_ADMIN_PASSWORD';

$conn = null;
$message = "";

try {
    $dsn = "sqlsrv:server=$host;Database=$db";
    $conn = new PDO($dsn, $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create table if it does not exist
    $conn->exec("IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='Entries' and xtype='U')
                 CREATE TABLE Entries (ID int IDENTITY(1,1) PRIMARY KEY, InputData varchar(255), CreatedAt datetime DEFAULT GETDATE())");

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['user_input'])) {
        $stmt = $conn->prepare("INSERT INTO Entries (InputData) VALUES (:data)");
        $stmt->execute(['data' => $_POST['user_input']]);
        $message = "Data successfully stored in Azure SQL!";
    }
} catch (Exception $e) {
    $message = "Database connection error: " . htmlspecialchars($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Enterprise PaaS - SQL Integration</title>
    <style>
        body { font-family: sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; background: #0f172a; color: #f8fafc; }
        .card { background: #1e293b; padding: 2.5rem; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); width: 400px; }
        h2 { color: #38bdf8; margin-top: 0; }
        input[type="text"] { width: 100%; padding: 0.75rem; margin: 0.5rem 0 1rem 0; background: #0f172a; border: 1px solid #334155; color: white; border-radius: 6px; box-sizing: border-box; }
        button { background: #38bdf8; color: #0f172a; border: none; padding: 0.75rem; font-weight: bold; border-radius: 6px; cursor: pointer; width: 100%; }
        button:hover { background: #7dd3fc; }
        .msg { background: #065f46; color: #34d399; padding: 0.5rem; border-radius: 4px; margin-bottom: 1rem; font-size: 0.875rem; text-align: center; }
        ul { padding-left: 20px; color: #94a3b8; max-height: 150px; overflow-y: auto; }
        li { margin-bottom: 0.25rem; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Azure SQL Input Form</h2>
        <?php if($message): ?><div class="msg"><?= $message ?></div><?php endif; ?>
        <form method="POST">
            <label>Enter Data:</label>
            <input type="text" name="user_input" placeholder="Type something..." required autocomplete="off">
            <button type="submit">Submit to Database</button>
        </form>
        
        <h3>Stored Database Records:</h3>
        <ul>
            <?php
            if ($conn) {
                try {
                    $stmt = $conn->query("SELECT InputData, CreatedAt FROM Entries ORDER BY CreatedAt DESC");
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        echo "<li>" . htmlspecialchars($row['InputData']) . " <small style='color: #64748b;'>(" . $row['CreatedAt'] . ")</small></li>";
                    }
                } catch (Exception $ex) {
                    echo "<li>Unable to fetch records.</li>";
                }
            }
            ?>
        </ul>
    </div>
</body>
</html>