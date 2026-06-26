<?php
$loginBackgroundPath = null;

$backgroundCandidates = [
    'assets/login-bg.jpg',
    'assets/login-bg.jpeg',
    'assets/login-bg.png',
    'assets/login-bg.webp',
    'assets/jengo.jpg',
    'assets/jengo.png',
    'assets/jengo.jpeg',
    'assets/jengo.webp',
    'assets/building.jpg',
    'assets/building.png',
    'assets/building.jpeg',
    'assets/building.webp',
    'login-bg.jpg',
    'jengo.jpg',
    'building.jpg'
];

foreach ($backgroundCandidates as $candidate) {
    if (file_exists(__DIR__ . '/' . $candidate)) {
        $loginBackgroundPath = $candidate;
        break;
    }
}

if (!$loginBackgroundPath) {
    $assetImages = glob(__DIR__ . '/assets/*.{jpg,jpeg,png,webp,gif}', GLOB_BRACE);
    if ($assetImages && count($assetImages) > 0) {
        usort($assetImages, function ($a, $b) {
            return filemtime($b) <=> filemtime($a);
        });

        foreach ($assetImages as $imagePath) {
            $fileName = strtolower(basename($imagePath));
            if ($fileName === 'logo.png') {
                continue;
            }
            $loginBackgroundPath = 'assets/' . basename($imagePath);
            break;
        }
    }
}

$whatsappBackgrounds = glob(__DIR__ . '/assets/WhatsApp Image*.{jpg,jpeg,png,webp,gif}', GLOB_BRACE);
if (!$loginBackgroundPath && $whatsappBackgrounds && count($whatsappBackgrounds) > 0) {
    usort($whatsappBackgrounds, function ($a, $b) {
        return filemtime($b) <=> filemtime($a);
    });
    $loginBackgroundPath = 'assets/' . basename($whatsappBackgrounds[0]);
}

if (!$loginBackgroundPath && file_exists(__DIR__ . '/assets/logo.png')) {
    $loginBackgroundPath = 'assets/logo.png';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Visitor Management System - Login</title>

<!-- Bootstrap -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<!-- Google Font -->
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<style>

:root{
    --brand-teal:#0f766e;
    --brand-sky:#0284c7;
    --brand-lime:#65a30d;
    --text-main:#f0fdfa;
    --text-soft:#c8f0ea;
    --card-bg:rgba(4,32,39,0.64);
    --field-bg:rgba(153,246,228,0.10);
    --field-bg-focus:rgba(153,246,228,0.20);
    --field-border:rgba(153,246,228,0.45);
}

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Outfit',sans-serif;
}

body{
    min-height:100dvh;
    background:
        radial-gradient(circle at 12% 15%, rgba(14,165,233,0.55) 0%, rgba(14,165,233,0) 34%),
        radial-gradient(circle at 86% 20%, rgba(245,158,11,0.38) 0%, rgba(245,158,11,0) 32%),
        linear-gradient(135deg,#082f49 0%, #0f766e 42%, #1d4ed8 100%);
    display:flex;
    justify-content:center;
    align-items:center;
    overflow-x:hidden;
    overflow-y:auto;
    padding:14px;
    position:relative;
}

body.has-custom-bg{
    background-image:
        radial-gradient(circle at 14% 22%, rgba(209,250,229,0.20) 0%, rgba(209,250,229,0) 38%),
        radial-gradient(circle at 82% 16%, rgba(186,230,253,0.14) 0%, rgba(186,230,253,0) 35%),
        linear-gradient(145deg, rgba(6,95,70,0.22), rgba(2,132,199,0.14)),
        var(--login-bg-url);
    background-size:100% auto;
    background-position:center center;
    background-repeat:no-repeat;
    background-color:#b7dfff;
}

body::before{
    content:'';
    position:absolute;
    inset:0;
    background:
        radial-gradient(circle at 18% 10%, rgba(255,255,255,0.24), rgba(255,255,255,0) 36%),
        radial-gradient(circle at 80% 88%, rgba(125,211,252,0.22), rgba(125,211,252,0) 42%);
    opacity:0.55;
    pointer-events:none;
}

body.has-custom-bg::before{
    opacity:0.18;
}

@media (max-width: 768px){
    body.has-custom-bg{
        background-size:cover;
        background-position:center center;
    }
}

body.has-custom-bg .bg-circle{
    display:none;
}

/* BACKGROUND CIRCLES */
.bg-circle{
    position:absolute;
    border-radius:50%;
    background:radial-gradient(circle at 30% 30%, rgba(255,255,255,0.7), rgba(255,255,255,0.1));
    filter:blur(1px);
    animation:float 8s infinite ease-in-out;
}

.bg-circle:nth-child(1){
    width:220px;
    height:220px;
    top:-50px;
    left:-45px;
}

.bg-circle:nth-child(2){
    width:320px;
    height:320px;
    bottom:-120px;
    right:-90px;
}

@keyframes float{
    0%{transform:translateY(0px);}
    50%{transform:translateY(20px);}
    100%{transform:translateY(0px);}
}

/* LOGIN CARD */
.login-card{
    width:100%;
    max-width:430px;
    max-height:calc(100dvh - 28px);
    overflow-y:auto;
    background:var(--card-bg);
    backdrop-filter:blur(14px) saturate(140%);
    border:1px solid rgba(153,246,228,0.40);
    border-radius:28px;
    padding:30px 28px;
    color:var(--text-main);
    box-shadow:0 24px 60px rgba(3,22,28,0.55);
    position:relative;
    z-index:10;
    animation:cardEntrance 0.7s ease-out;
}

.login-card::after{
    content:'';
    position:absolute;
    inset:0;
    border-radius:28px;
    padding:1px;
    background:linear-gradient(130deg, rgba(34,197,94,0.36), rgba(6,182,212,0.18), rgba(14,116,144,0.34));
    -webkit-mask:linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
    -webkit-mask-composite:xor;
    mask-composite:exclude;
    pointer-events:none;
}

@keyframes cardEntrance{
    from{
        opacity:0;
        transform:translateY(18px) scale(0.98);
    }
    to{
        opacity:1;
        transform:translateY(0) scale(1);
    }
}

.system-logo{
    width:94px;
    height:94px;
    background:rgba(255,255,255,0.92);
    border-radius:18px;
    display:flex;
    align-items:center;
    justify-content:center;
    margin:0 auto 18px;
    box-shadow:0 12px 28px rgba(2,6,23,0.38);
    overflow:hidden;
    border:2px solid rgba(153,246,228,0.58);
}

.system-logo i{
    font-size:40px;
    color:#0f766e;
}

.login-logo-img{
    width:100%;
    height:100%;
    object-fit:contain;
    display:block;
}

.system-title{
    text-align:center;
    font-size:28px;
    font-weight:800;
    margin-bottom:6px;
    letter-spacing:0.2px;
    text-shadow:0 4px 18px rgba(15,23,42,0.28);
}

.system-subtitle{
    text-align:center;
    color:var(--text-soft);
    margin-bottom:18px;
    font-size:13px;
}

.form-label{
    font-weight:600;
    color:#e6fffb;
    margin-bottom:8px;
}

.input-group{
    margin-bottom:12px;
}

.input-group-text{
    background:var(--field-bg);
    border:1px solid var(--field-border);
    border-right:none;
    color:#d1fae5;
    border-radius:12px 0 0 12px;
}

.form-control{
    background:var(--field-bg);
    border:1px solid var(--field-border);
    color:var(--text-main);
    height:46px;
    border-radius:0 12px 12px 0;
}

.password-input{
    border-radius:0;
}

.toggle-password{
    background:var(--field-bg);
    border:1px solid var(--field-border);
    border-left:none;
    color:#d1fae5;
    border-radius:0 12px 12px 0;
}

.toggle-password:hover,
.toggle-password:focus{
    background:var(--field-bg-focus);
    color:#ffffff;
    box-shadow:none;
}

.form-control::placeholder{
    color:#a7d8d0;
}

.form-control:focus{
    box-shadow:none;
    background:var(--field-bg-focus);
    border-color:rgba(45,212,191,0.9);
    color:var(--text-main);
}

.login-btn{
    width:100%;
    height:46px;
    border:none;
    border-radius:16px;
    background:linear-gradient(135deg,var(--brand-lime),var(--brand-teal),var(--brand-sky));
    color:#ffffff;
    font-weight:700;
    font-size:16px;
    letter-spacing:0.3px;
    transition:0.3s;
    box-shadow:0 12px 30px rgba(15,118,110,0.45);
}

.login-btn:hover{
    filter:brightness(1.12) saturate(1.05);
    transform:translateY(-2px);
}

.footer-text{
    text-align:center;
    margin-top:16px;
    font-size:13px;
    color:#b9e7de;
}

.security-box{
    margin-top:14px;
    background:rgba(3,24,32,0.42);
    border:1px solid rgba(110,231,183,0.34);
    padding:10px;
    border-radius:12px;
    text-align:center;
    font-size:13px;
    color:#d1fae5;
}
.forgot-link {
    display: inline-block;
    margin-top: 12px;
    font-size: 15px;
    font-weight: 600;
    color: #99f6e4;
    text-decoration: none;
    transition: 0.3s ease;
}

.forgot-link:hover {
    color: #d9f99d;
    text-decoration: underline;
    transform: translateY(-1px);
}

@media (max-width: 576px){
    .login-card{
        max-height:calc(100dvh - 20px);
        padding:22px 16px;
        border-radius:20px;
    }

    .login-card::after{
        border-radius:20px;
    }

    .system-logo{
        width:92px;
        height:92px;
    }

    .system-title{
        font-size:26px;
    }
}

</style>
</head>
<body class="has-custom-bg" style="--login-bg-url:url('<?php echo $loginBackgroundPath ?: 'assets/logo.png'; ?>?t=<?php echo time(); ?>');">

<div class="bg-circle"></div>
<div class="bg-circle"></div>

<div class="login-card">

    <!-- LOGO: prefer assets/logo.png, else first visitor_photos image, else icon -->
    <div class="system-logo">
        <?php
        $logoPath = null;
        if (file_exists(__DIR__ . '/assets/logo.png')) {
            $logoPath = 'assets/logo.png';
        } else {
            $pics = glob(__DIR__ . '/visitor_photos/*.{png,jpg,jpeg,gif,svg}', GLOB_BRACE);
            if ($pics && count($pics) > 0) {
                $logoPath = 'visitor_photos/' . basename($pics[0]);
            }
        }
        ?>

        <?php if ($logoPath): ?>
            <img src="<?php echo $logoPath; ?>?t=<?php echo time(); ?>" alt="Logo" class="login-logo-img">
        <?php else: ?>
            <i class="fas fa-user-shield"></i>
        <?php endif; ?>
    </div>

    <!-- TITLE -->
    <h1 class="system-title">Visitor MS</h1>
    <p class="system-subtitle">
        Smart Visitor Management & Security System
    </p>

    <!-- LOGIN FORM -->
    <form action="login_process.php" method="POST">

        <!-- USERNAME -->
        <label class="form-label">Username</label>

        <div class="input-group">
            <span class="input-group-text">
                <i class="fas fa-user"></i>
            </span>

            <input type="text"
                   name="username"
                   class="form-control"
                   placeholder="Enter username"
                   required>
        </div>

        <!-- PASSWORD -->
        <label class="form-label">Password</label>

        <div class="input-group">
            <span class="input-group-text">
                <i class="fas fa-lock"></i>
            </span>

            <input type="password"
                   name="password"
                   class="form-control password-input"
                   id="passwordField"
                   placeholder="Enter password"
                   required>

            <button class="btn toggle-password"
                    type="button"
                    id="togglePassword"
                    aria-label="Show password">
                <i class="fas fa-eye" id="togglePasswordIcon"></i>
            </button>
        </div>
        <a href="forgot_password.php" class="forgot-link">
    Forgot Password?
</a>

        <!-- LOGIN BUTTON -->
        <button type="submit" class="login-btn">
            <i class="fas fa-right-to-bracket"></i>
            Login System
        </button>

    </form>

    <!-- SECURITY BOX -->
    <div class="security-box">
        <i class="fas fa-shield-halved"></i>
        Secure Access | Authorized Users Only
    </div>

    <!-- FOOTER -->
    <div class="footer-text">
        © 2026 Visitor Management System
    </div>

</div>

<script>
const passwordField = document.getElementById('passwordField');
const togglePassword = document.getElementById('togglePassword');
const togglePasswordIcon = document.getElementById('togglePasswordIcon');

togglePassword.addEventListener('click', () => {
    const isPassword = passwordField.type === 'password';
    passwordField.type = isPassword ? 'text' : 'password';
    togglePasswordIcon.classList.toggle('fa-eye');
    togglePasswordIcon.classList.toggle('fa-eye-slash');
    togglePassword.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
});
</script>

</body>
</html>
