<?php
// skill-swap-platform/index.php
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Skill Swap Platform</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

    <style>
        /* Reset & Base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea, #764ba2);
            min-height: 100vh;
            color: #fff;
        }

        /* Navbar */
        .navbar {
            width: 100%;
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
        }

        .navbar .logo {
            font-size: 1.8rem;
            font-weight: 700;
            color: #fff;
            animation: textGlow 2s infinite alternate;
        }

        @keyframes textGlow {
            from { text-shadow: 0 0 5px #fff, 0 0 10px #36d1dc; }
            to { text-shadow: 0 0 15px #fff, 0 0 25px #5b86e5; }
        }

        .navbar .login-btn {
            padding: 10px 25px;
            background: linear-gradient(45deg, #fc466b, #3f5efb);
            border: none;
            border-radius: 30px;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            text-decoration: none;
        }

        .navbar .login-btn:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
        }

        /* Main Container */
        .container {
            text-align: center;
            padding: 100px 30px 40px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            animation: fadeIn 1.5s ease-in-out;
        }

        h1 {
            font-size: 3rem;
            margin-bottom: 20px;
            animation: slideDown 1s ease-out;
        }

        p {
            font-size: 1.2rem;
            margin-bottom: 40px;
            line-height: 1.6;
            animation: slideUp 1s ease-out;
            max-width: 600px;
        }

        .buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 30px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            border-radius: 30px;
            border: none;
            color: white;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .btn.signup {
            background: linear-gradient(45deg, #ff6a00, #ee0979);
            box-shadow: 0 4px 15px rgba(255,106,0,0.4);
        }

        .btn.explore {
            background: linear-gradient(45deg, #36d1dc, #5b86e5);
            box-shadow: 0 4px 15px rgba(54,209,220,0.4);
        }

        .btn:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-50px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(50px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive */
        @media (max-width: 600px) {
            h1 {
                font-size: 2.2rem;
            }

            p {
                font-size: 1rem;
            }

            .buttons {
                flex-direction: column;
            }

            .navbar {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .navbar .logo {
                font-size: 1.5rem;
            }
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <div class="navbar">
        <div class="logo">Skill Swap</div>
        <a href="screens/login.php" class="login-btn">Login</a>
    </div>

    <!-- Main Content -->
    <div class="container">
        <h1>Welcome to Skill Swap Platform</h1>
        <p>Exchange skills, make connections, and empower each other.<br>
        Teach what you know, learn what you love — all in one community.</p>

        <div class="buttons">
            <a href="screens/signup.php" class="btn signup">Sign Up</a>
            <a href="screens/browse_skills.php" class="btn explore">Explore Skills</a>
        </div>
    </div>
</body>
</html>
