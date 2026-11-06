<!doctype html>
<html lang="fr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width"></head>
<body style="margin:0;background:#f1f1f1;font-family:Inter,Arial,sans-serif;color:#303030">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:32px 16px">
    <tr><td align="center">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background:#fff;border-radius:12px;padding:28px">
        <tr><td>
          <p style="margin:0 0 16px;font-size:15px">Bonjour{{ $inv->name ? ' '.$inv->name : '' }},</p>
          <p style="margin:0 0 16px;font-size:15px;line-height:1.5">
            {{ $inviter ? $inviter.' vous invite' : 'Vous êtes invité' }} à rejoindre {{ $where }} sur picstosell, en tant que <strong>{{ $inv->roleLabel() }}</strong>.
          </p>
          <p style="margin:24px 0">
            <a href="{{ $url }}" style="display:inline-block;background:#303030;color:#fff;text-decoration:none;font-weight:600;font-size:14px;padding:10px 18px;border-radius:8px">Créer mon accès</a>
          </p>
          <p style="margin:0 0 8px;font-size:13px;color:#616161;line-height:1.5">Vous choisirez votre mot de passe en ouvrant le lien. Il reste valable {{ $days }} jours et ne sert qu'une fois.</p>
          <p style="margin:0;font-size:12px;color:#8a8a8a;word-break:break-all">{{ $url }}</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
