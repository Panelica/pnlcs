<!DOCTYPE html>
<html lang="en" data-theme="{{ request()->cookie('pnlcs_theme') === 'dark' ? 'dark' : 'light' }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Reset Password - PNLCS</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:Inter,sans-serif;background:#f0f4f8;display:flex;align-items:center;justify-content:center;min-height:100vh}.card{background:#fff;border-radius:16px;box-shadow:0 8px 30px rgba(0,0,0,.08);width:420px;max-width:95%;padding:40px}h1{font-size:22px;font-weight:700;color:#1a4d80;margin-bottom:24px}.fg{margin-bottom:16px}.fl{display:block;font-size:13px;font-weight:600;color:#334155;margin-bottom:6px}.fc{width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none}.fc:focus{border-color:#1a4d80}.btn{width:100%;padding:12px;background:#1a4d80;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer}.btn:hover{background:#143d66}.ae{background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;padding:12px;border-radius:8px;margin-bottom:16px;font-size:13px}</style></head>
<body><div class="card"><h1>Reset Password</h1>
<?php if($errors->any()): ?><div class="ae"><?php foreach($errors->all() as $e): ?><?=$e?> <?php endforeach; ?></div><?php endif; ?>
<form method="POST" action="<?=route('client.password.update.reset')?>"><?php echo csrf_field(); ?>
<input type="hidden" name="token" value="<?=$token?>">
<div class="fg"><label class="fl">{{ __('common.form.email') }}</label><input type="email" name="email" class="fc" value="<?=$email ?? old('email')?>" required></div>
<div class="fg"><label class="fl">{{ __('common.form.new_password') }}</label><input type="password" name="password" class="fc" minlength="8" required></div>
<div class="fg"><label class="fl">{{ __('common.form.confirm_password') }}</label><input type="password" name="password_confirmation" class="fc" required></div>
<button type="submit" class="btn">Reset Password</button></form></div></body></html>
