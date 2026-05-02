<?php include 'database.php'; $msg='';
if ($_SERVER['REQUEST_METHOD']==='POST'){
    $users=[['admin','headworker','Admin Système','1234'],['magasin1','warehouse_worker','Ouvrier Magasin 1','1234'],['magasin2','warehouse_worker','Ouvrier Magasin 2','1234'],['ligne1','line_worker','Ouvrier Ligne 1','1234'],['ligne2','line_worker','Ouvrier Ligne 2','1234']];
    foreach($users as [$u,$r,$fn,$p]){$hash=password_hash($p,PASSWORD_DEFAULT);$ex=$pdo->prepare("SELECT id FROM users WHERE username=?");$ex->execute([$u]);
    if($ex->fetch()){$pdo->prepare("UPDATE users SET password=?,role=?,full_name=? WHERE username=?")->execute([$hash,$r,$fn,$u]);}
    else{$pdo->prepare("INSERT INTO users (username,password,role,full_name) VALUES (?,?,?,?)")->execute([$u,$hash,$r,$fn]);}}
    $msg='Comptes créés. Mot de passe : 1234';
}
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Reset — AGV</title>
<?php include '_css.php'; ?>
<style>body{display:flex;align-items:center;justify-content:center;min-height:100vh;padding:1rem}</style>
</head><body>
<div style="max-width:440px;width:100%">
<?php if($msg):?><div class="alert a-ok">✅ <?= sanitize($msg) ?> — <a href="login.php" style="color:var(--grn-d);font-weight:700">Se connecter</a></div><?php endif;?>
<div class="panel"><div class="ph"><span class="pt">🔑 Réinitialiser les comptes</span></div>
<div class="pb">
<p style="font-size:.85rem;color:var(--txt2);margin-bottom:1rem">Crée tous les comptes avec le mot de passe <strong>1234</strong></p>
<div style="font-size:.82rem;margin-bottom:1rem;border:1px solid var(--bdr);border-radius:8px;overflow:hidden">
<div style="padding:.5rem 1rem;border-bottom:1px solid var(--bdr)">👑 <strong>admin</strong> — headworker</div>
<div style="padding:.5rem 1rem;border-bottom:1px solid var(--bdr)">📦 <strong>magasin1, magasin2</strong> — warehouse_worker</div>
<div style="padding:.5rem 1rem">🏭 <strong>ligne1, ligne2</strong> — line_worker</div>
</div>
<form method="POST"><button type="submit" class="btn btn-o btn-fw">🔑 Créer / Réinitialiser tous les comptes</button></form>
</div></div></div>
</body></html>
