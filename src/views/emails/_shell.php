<?php
/**
 * Email shell. Call from a template:
 *   <?php ob_start(); ?> …inner html… <?php $inner = ob_get_clean();
 *   require __DIR__ . '/_shell.php';
 * with $emailTitle set. Inline styles only — email clients ignore <style>.
 */
$inner = $inner ?? '';
$emailTitle = $emailTitle ?? AFT_NAME;
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"></head>
<body style="margin:0;background:#F4F1EA;font-family:Arial,Helvetica,sans-serif;color:#131313;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F4F1EA;padding:24px 0;">
    <tr><td align="center">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid rgba(14,14,14,.1);">
        <tr><td style="background:#0E0E0E;padding:24px 32px;">
          <span style="color:#ffffff;font-size:20px;font-weight:bold;letter-spacing:-.5px;">Afrotech <span style="color:#E4022B;">Academy</span></span>
        </td></tr>
        <tr><td style="padding:32px;">
          <?= $inner ?>
        </td></tr>
        <tr><td style="background:#0E0E0E;padding:20px 32px;color:rgba(255,255,255,.7);font-size:12px;line-height:1.6;">
          <?= e(AFT_PARENT) ?> · <em>Building brands, strengthening legacies</em><br>
          <?= e(AFT_PHONE) ?> · <a href="mailto:<?= e(AFT_EMAIL) ?>" style="color:#FF6B7A;"><?= e(AFT_EMAIL) ?></a>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body></html>
