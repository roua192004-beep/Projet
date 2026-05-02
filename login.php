<?php
session_start(); include 'database.php';
if (isset($_SESSION['role'])) { header($_SESSION['role']==='headworker'?'Location: admin_dashboard.php':'Location: mobile_dashboard.php'); exit(); }
$err='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $u=trim($_POST['username']??''); $p=trim($_POST['password']??'');
    if ($u&&$p) {
        $s=$pdo->prepare("SELECT * FROM users WHERE username=? LIMIT 1"); $s->execute([$u]); $usr=$s->fetch();
        if ($usr&&password_verify($p,$usr['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']=$usr['id']; $_SESSION['role']=$usr['role'];
            $_SESSION['username']=$usr['username']; $_SESSION['full_name']=$usr['full_name'];
            header($usr['role']==='headworker'?'Location: admin_dashboard.php':'Location: mobile_dashboard.php'); exit();
        }
        $err='Identifiant ou mot de passe incorrect.'; sleep(1);
    } else { $err='Remplir tous les champs.'; }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sagemcom Station de Contrôle — Connexion</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest"></script>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --bg-color: #F8FAFC;
  --card-bg: #FFFFFF;
  --bdr: #E2E8F0;
  --bdr-focus: #BFDBFE;
  --txt-main: #0F172A;
  --txt-mut: #64748B;
  
  /* Nouveau Bleu plus foncé pour les boutons */
  --acc-main: #1E3A8A;
  --acc-hover: #172554;
  --primary-glow: rgba(30, 58, 138, 0.25);
  
  /* Icônes de rôles en bleu également */
  --role-icon-bg: #EFF6FF;
  --role-icon-txt: #1E3A8A;
}

body {
  font-family: 'Inter', sans-serif;
  color: var(--txt-main);
  background: var(--bg-color);
  -webkit-font-smoothing: antialiased;
  height: 100vh;
  overflow: hidden;
}

.split-layout {
  display: flex;
  height: 100%;
  width: 100%;
}

/* LEFT SIDE - DEEP DARK BLUE Sagemcom */
.split-left {
  flex: 1.2;
  background: linear-gradient(135deg, #0B1121, #1E3A8A);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  overflow: hidden;
}

.split-left::before {
  content: '';
  position: absolute;
  top: -10%; left: -10%;
  width: 60vw; height: 60vw;
  background: radial-gradient(circle, rgba(255,255,255,0.03) 0%, transparent 60%);
  border-radius: 50%;
}
.split-left::after {
  content: '';
  position: absolute;
  bottom: -15%; right: -10%;
  width: 40vw; height: 40vw;
  background: radial-gradient(circle, rgba(30,58,138,0.4) 0%, transparent 60%);
  border-radius: 50%;
}

.left-content {
  position: relative;
  z-index: 1;
  text-align: center;
  max-width: 600px;
  padding: 2rem;
}

.left-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 80px; height: 80px;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 20px;
  backdrop-filter: blur(10px);
  margin-bottom: 2rem;
  box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}

.left-title {
  font-size: 3.5rem;
  font-weight: 800;
  line-height: 1.1;
  margin-bottom: 0;
  letter-spacing: -0.03em;
}

.left-title-sub {
  font-size: 1.8rem;
  font-weight: 400;
  opacity: 0.9;
  letter-spacing: 0;
  display: block;
  margin-top: 0.5rem;
}

/* RIGHT SIDE - LOGIN FORM */
.split-right {
  flex: 1;
  background: var(--bg-color);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem;
  position: relative;
}

.bg-pattern {
  position: absolute;
  inset: 0;
  z-index: 0;
  background-size: 40px 40px;
  background-image: linear-gradient(to right, rgba(15, 23, 42, 0.03) 1px, transparent 1px),
                    linear-gradient(to bottom, rgba(15, 23, 42, 0.03) 1px, transparent 1px);
  mask-image: radial-gradient(circle at center, black 40%, transparent 100%);
}

.login-wrapper {
  width: 100%;
  max-width: 440px;
  position: relative;
  z-index: 1;
}

.card {
  background: var(--card-bg);
  border: 1px solid rgba(255, 255, 255, 0.8);
  border-radius: 20px;
  box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.1), 0 0 0 1px rgba(15, 23, 42, 0.04);
  overflow: hidden;
}

.card-header {
  padding: 2.5rem 2.5rem 1.5rem;
  text-align: center;
}

.app-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--txt-main);
  letter-spacing: -0.02em;
  margin-bottom: 0.25rem;
}

.app-subtitle {
  font-size: 0.85rem;
  font-weight: 500;
  color: var(--txt-mut);
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.card-body {
  padding: 0 2.5rem 1.5rem;
}

.alert-error {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.875rem 1rem;
  background: #FEF2F2;
  border: 1px solid #FECACA;
  border-radius: 10px;
  color: #B91C1C;
  font-size: 0.85rem;
  font-weight: 500;
  margin-bottom: 1.5rem;
  animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
  from { opacity: 0; transform: translateY(-5px); }
  to { opacity: 1; transform: translateY(0); }
}

.field {
  margin-bottom: 1.25rem;
  position: relative;
}

.field-label {
  display: block;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--txt-mut);
  margin-bottom: 0.4rem;
  transition: color 0.2s;
}

.input-icon-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.input-icon {
  position: absolute;
  left: 1rem;
  color: #94A3B8;
  width: 18px;
  height: 18px;
  transition: color 0.2s;
  pointer-events: none;
}

.inp {
  width: 100%;
  padding: 0.875rem 1rem 0.875rem 2.75rem;
  background: #F8FAFC;
  border: 1px solid var(--bdr);
  border-radius: 10px;
  color: var(--txt-main);
  font-size: 0.95rem;
  font-family: 'Inter', sans-serif;
  transition: all 0.2s ease;
  outline: none;
}

.inp:hover {
  border-color: var(--bdr-focus);
}

.inp:focus {
  border-color: var(--acc-main);
  background: #FFFFFF;
  box-shadow: 0 0 0 4px var(--primary-glow);
}

.inp:focus + .input-icon {
  color: var(--acc-main);
}

.inp:focus-within ~ .field-label {
  color: var(--txt-main);
}

.inp::placeholder {
  color: #94A3B8;
}

.btn-submit {
  width: 100%;
  padding: 0.875rem;
  margin-top: 0.5rem;
  background: linear-gradient(135deg, var(--acc-main), var(--acc-hover));
  border: none;
  border-radius: 10px;
  color: #FFFFFF;
  font-size: 0.95rem;
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  transition: all 0.2s ease;
  box-shadow: 0 4px 12px var(--primary-glow);
}

.btn-submit:hover {
  transform: translateY(-1px);
  box-shadow: 0 6px 16px rgba(30, 58, 138, 0.4);
  filter: brightness(1.1);
}

.btn-submit:active {
  transform: translateY(0);
  box-shadow: 0 2px 8px var(--primary-glow);
}

.card-footer {
  padding: 1.5rem 2.5rem;
  border-top: 1px solid var(--bdr);
  background: #F8FAFC;
}

.roles-title {
  font-size: 0.65rem;
  font-weight: 700;
  color: var(--txt-mut);
  text-transform: uppercase;
  letter-spacing: 0.08em;
  margin-bottom: 1rem;
  text-align: center;
}

.roles-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.75rem;
}

.role-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 0.875rem 0.5rem;
  background: #FFFFFF;
  border: 1px solid var(--bdr);
  border-radius: 10px;
  transition: all 0.2s;
  box-shadow: 0 1px 2px rgba(0,0,0,0.02);
  cursor: pointer;
  text-align: center;
}

.role-btn:hover {
  border-color: var(--bdr-focus);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.04);
  background: #F1F5F9;
}

.role-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 8px;
  margin-bottom: 0.5rem;
  background: var(--role-icon-bg);
  color: var(--role-icon-txt);
}

.role-name {
  font-size: 0.7rem;
  font-weight: 700;
  color: var(--txt-main);
  line-height: 1.2;
}

.role-sub {
  font-size: 0.6rem;
  color: var(--txt-mut);
  font-family: 'JetBrains Mono', monospace;
  margin-top: 0.2rem;
}

/* Responsiveness for mobile/tablets */
@media (max-width: 900px) {
  body { overflow: auto; height: auto; min-height: 100vh; }
  .split-layout { flex-direction: column; }
  .split-left {
    flex: none;
    padding: 4rem 2rem;
  }
  .left-title { font-size: 2.5rem; }
  .left-title-sub { font-size: 1.4rem; }
  .split-right {
    flex: 1;
    border-top-left-radius: 24px;
    border-top-right-radius: 24px;
    margin-top: -24px;
    padding: 2rem 1rem;
    box-shadow: 0 -10px 40px rgba(0,0,0,0.1);
  }
}
</style>
</head>
<body>

<div class="split-layout">

  <div class="split-left">
    <div class="left-content">
      <div class="left-icon">
        <i data-lucide="cpu" style="width: 40px; height: 40px; color: white;"></i>
      </div>
      <h1 class="left-title">
        Sagemcom<br>
        <span class="left-title-sub">Station de Contrôle</span>
      </h1>
    </div>
  </div>

  <div class="split-right">
    <div class="bg-pattern"></div>
    <div class="login-wrapper">
      <div class="card">
        <div class="card-header">
          <h2 class="app-title">Bienvenue</h2>
          <p class="app-subtitle">Veuillez vous identifier</p>
        </div>
        
        <div class="card-body">
          <?php if ($err): ?>
          <div class="alert-error">
            <i data-lucide="alert-circle" style="width: 18px; height: 18px;"></i>
            <span><?= sanitize($err) ?></span>
          </div>
          <?php endif; ?>
          
          <form method="POST" autocomplete="off" id="loginForm">
            <div class="field">
              <label class="field-label">Identifiant</label>
              <div class="input-icon-wrapper">
                <input class="inp" type="text" id="usernameInput" name="username" placeholder="admin, magasin1, ligne1..." value="<?= sanitize($_POST['username']??'') ?>" required autofocus>
                <i data-lucide="user" class="input-icon"></i>
              </div>
            </div>
            
            <div class="field">
              <label class="field-label">Mot de passe</label>
              <div class="input-icon-wrapper">
                <input class="inp" type="password" id="passwordInput" name="password" placeholder="••••••••" required>
                <i data-lucide="lock" class="input-icon"></i>
              </div>
            </div>
            
            <button type="submit" class="btn-submit">
              <span>Se connecter</span>
              <i data-lucide="arrow-right" style="width: 18px; height: 18px;"></i>
            </button>
          </form>
        </div>
        
        <div class="card-footer">
          <div class="roles-title">Sélection Rapide du Profil</div>
          <div class="roles-grid">
            <button type="button" class="role-btn" onclick="fillLogin('admin')">
              <div class="role-icon"><i data-lucide="shield-check" style="width:16px;height:16px"></i></div>
              <span class="role-name">Responsable</span>
              <span class="role-sub">admin</span>
            </button>
            <button type="button" class="role-btn" onclick="fillLogin('magasin1')">
              <div class="role-icon"><i data-lucide="package" style="width:16px;height:16px"></i></div>
              <span class="role-name">Magasinier</span>
              <span class="role-sub">magasin1</span>
            </button>
            <button type="button" class="role-btn" onclick="fillLogin('ligne1')">
              <div class="role-icon"><i data-lucide="factory" style="width:16px;height:16px"></i></div>
              <span class="role-name">Conducteur<br>de ligne</span>
              <span class="role-sub">ligne1</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>

<script>
  lucide.createIcons();

  function fillLogin(username) {
    const userInput = document.getElementById('usernameInput');
    const passInput = document.getElementById('passwordInput');
    
    userInput.value = username;
    
    // Animation de validation en bleu clair au lieu du jaune
    userInput.style.backgroundColor = '#EFF6FF';
    setTimeout(() => {
      userInput.style.backgroundColor = '#F8FAFC';
    }, 300);

    passInput.focus();
  }
</script>
</body>
</html>
