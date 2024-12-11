@include("emails.partials.header")

<table align="center" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td valign="top">
            Please use the below code to change your password from the app
        </td>
    </tr>
    <tr>
        <td valign="top">code : {{$code}}</td>
    </tr>
</table>

@include("emails.partials.footer")