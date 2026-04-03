<!DOCTYPE html>
<html lang="en" data-theme="{{ request()->cookie('pnlcs_theme') === 'dark' ? 'dark' : 'light' }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Forgot Password - PNLCS</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:Inter,sans-serif;background:#f0f4f8;display:flex;align-items:center;justify-content:center;min-height:100vh}.card{background:#fff;border-radius:16px;box-shadow:0 8px 30px rgba(0,0,0,.08);width:420px;max-width:95%;padding:40px}h1{font-size:22px;font-weight:700;color:#1a4d80;margin-bottom:6px}p.sub{color:#64748b;font-size:14px;margin-bottom:24px}.fg{margin-bottom:16px}.fl{display:block;font-size:13px;font-weight:600;color:#334155;margin-bottom:6px}.fc{width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none}.fc:focus{border-color:#1a4d80}.btn{width:100%;padding:12px;background:#1a4d80;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer}.btn:hover{background:#143d66}.as{background:#d1fae5;border:1px solid #6ee7b7;color:#065f46;padding:12px;border-radius:8px;margin-bottom:16px;font-size:13px}.ae{background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;padding:12px;border-radius:8px;margin-bottom:16px;font-size:13px}a{color:#1a4d80;text-decoration:none;font-weight:500;font-size:13px}</style></head>
<body><div class="card"><h1>Forgot Password</h1><p class="sub">Enter your email and we'll send you a reset link.</p>
<?php if(session('success')): ?><div class="as"><?=session('success')?></div><?php endif; ?>
<?php if($errors->any()): ?><div class="ae"><?php foreach($errors->all() as $e): ?><?=$e?><?php endforeach; ?></div><?php endif; ?>
<form method="POST" action="<?=route('client.password.email')?>"><?php echo csrf_field(); ?>
<div class="fg"><label class="fl">Email Address</label><input type="email" name="email" class="fc" placeholder="you@example.com" required></div>
<button type="submit" class="btn">Send Reset Link</button></form>
<div style="text-align:center;margin-top:20px"><a href="<?=route('client.login')?>">Back to Login</a></div></div></body></html>
