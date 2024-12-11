<!DOCTYPE html>
<html lang="en-US">
    <head>
        <meta charset="utf-8">
    </head>
    <body>

        <h3> Hello {{$user->name}},</h3>

        <div>Welcome to e-CAP, your "{{$role}}" account has just been created. Please find below your credentials and the link to access e-CAP site:</div>
        <br>
        <br>
        <div>Email : {{$user->email}}</div>
        <div>Password : {{$password}}</div>
        <div>Link : {{$app_url}}</div>
        <br>
        <br>
        <div>Regards,</div>
        <div>e-CAP team</div>
    </body>
</html>