<?php
/**
 * 管理员登录页
 */
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

// 已登录则跳转到控制面板
if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

// 处理登录请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    if (empty($username) || empty($password)) {
        $error = '请输入用户名和密码';
    } else {
        // 查询管理员
        $sql = "SELECT admin_id, username, password FROM admins WHERE username = ?";
        $stmt = prepareAndExecute($sql, 's', [$username]);
        
        if ($stmt) {
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $admin = $result->fetch_assoc();
                
                // 验证密码
                if (md5($password) === $admin['password']) {
                    // 登录成功
                    $_SESSION['admin_id'] = $admin['admin_id'];
                    $_SESSION['admin_username'] = $admin['username'];
                    
                    header('Location: dashboard.php');
                    exit;
                } else {
                    $error = '密码错误';
                }
            } else {
                $error = '用户名不存在';
            }
        } else {
            $error = '登录失败，请稍后重试';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员登录 - <?php echo SITE_TITLE; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            font-family: 'PingFang SC', 'Microsoft YaHei', sans-serif;
            background-color: #f5f5f7;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        
        .login-container {
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            width: 360px;
            padding: 32px;
            text-align: center;
        }
        
        .logo {
            margin-bottom: 24px;
        }
        
        h1 {
            font-size: 24px;
            font-weight: 500;
            color: #1d1d1f;
            margin-bottom: 24px;
        }
        
        .error-message {
            background-color: #ffebee;
            color: #d32f2f;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 16px;
            text-align: left;
        }
        
        label {
            display: block;
            font-size: 14px;
            color: #6e6e73;
            margin-bottom: 8px;
        }
        
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            font-size: 16px;
            border: 1px solid #d2d2d7;
            border-radius: 8px;
            background-color: #fff;
            box-sizing: border-box;
            transition: border-color 0.2s;
        }
        
        input[type="text"]:focus,
        input[type="password"]:focus {
            border-color: #0071e3;
            outline: none;
        }
        
        button {
            background-color: #0071e3;
            color: #fff;
            border: none;
            padding: 12px 24px;
            font-size: 16px;
            font-weight: 500;
            border-radius: 8px;
            cursor: pointer;
            width: 100%;
            margin-top: 8px;
            transition: background-color 0.2s;
        }
        
        button:hover {
            background-color: #0058b9;
        }
        
        .back-link {
            margin-top: 24px;
            font-size: 14px;
        }
        
        .back-link a {
            color: #0071e3;
            text-decoration: none;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <svg width="64" height="64" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect width="64" height="64" rx="14" fill="#0071E3"/>
                <path d="M20 32C20 25.373 25.373 20 32 20C38.627 20 44 25.373 44 32C44 38.627 38.627 44 32 44C25.373 44 20 38.627 20 32Z" stroke="white" stroke-width="3"/>
                <path d="M32 20V44" stroke="white" stroke-width="3"/>
                <path d="M20 32H44" stroke="white" stroke-width="3"/>
                <path d="M32 20L44 32L32 44L20 32L32 20Z" stroke="white" stroke-width="3"/>
            </svg>
        </div>
        <h1>管理员登录</h1>
        
        <?php if (!empty($error)): ?>
            <div class="error-message"><?php echo h($error); ?></div>
        <?php endif; ?>
        
        <form method="post" action="">
            <div class="form-group">
                <label for="username">用户名</label>
                <input type="text" id="username" name="username" placeholder="请输入用户名" value="<?php echo isset($_POST['username']) ? h($_POST['username']) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password">密码</label>
                <input type="password" id="password" name="password" placeholder="请输入密码" required>
            </div>
            
            <button type="submit">登录</button>
        </form>
        
        <div class="back-link">
            <a href="../index.php">返回首页</a>
        </div>
    </div>
    
    <script src="../assets/js/script.js"></script>
</body>
</html> 