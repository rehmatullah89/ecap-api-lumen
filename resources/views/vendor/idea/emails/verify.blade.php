@include("emails.partials.header")
<table align="center" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td valign="top">
            Please use the below code to verify your email
        </td>
    </tr>
    <tr>
        <td valign="top">code : {{$code}}</td>
    </tr>
    <tr>
        <td valign="top">
            Confirm your email address to continue creating and sharing looks on Topshou.
        </td>
    </tr>
</table>
@include("emails.partials.footer")