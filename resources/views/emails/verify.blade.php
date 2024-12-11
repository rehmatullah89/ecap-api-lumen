@include("emails.partials.header")
<tr>
    <td align="center" valign="top" id="templatePreheader">
        <!--[if gte mso 9]>
        <table align="center" border="0" cellspacing="0" cellpadding="0" width="600"
               style="width:600px;">
            <tr>
                <td align="center" valign="top" width="600" style="width:600px;">
        <![endif]-->
        <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%"
               class="templateContainer">
            <tr>
                <td valign="top" class="preheaderContainer">
                    <table border="0" cellpadding="0" cellspacing="0" width="100%"
                           class="mcnTextBlock" style="min-width:100%;">
                        <tbody class="mcnTextBlockOuter">
                        <tr>
                            <td valign="top" class="mcnTextBlockInner" style="padding-top:9px;">
                                <!--[if mso]>
                                <table align="left" border="0" cellspacing="0" cellpadding="0"
                                       width="100%" style="width:100%;">
                                    <tr>
                                <![endif]-->

                                <!--[if mso]>
                                <td valign="top" width="390" style="width:390px;">
                                <![endif]-->
                                <table align="left" border="0" cellpadding="0" cellspacing="0"
                                       style="max-width:390px;" width="100%"
                                       class="mcnTextContentContainer">
                                    <tbody>
                                    <tr>

                                        <td valign="top" class="mcnTextContent"
                                            style="padding-top:0; padding-left:18px; padding-bottom:9px; padding-right:18px;">

                                            TopShou, Verify Email
                                        </td>
                                    </tr>
                                    </tbody>
                                </table>
                                <!--[if mso]>
                                </td>
                                <![endif]-->

                                <!--[if mso]>
                                <td valign="top" width="210" style="width:210px;">
                                <![endif]-->
                                <table align="left" border="0" cellpadding="0" cellspacing="0"
                                       style="max-width:210px;" width="100%"
                                       class="mcnTextContentContainer">
                                    <tbody>
                                    <tr>

                                        <td valign="top" class="mcnTextContent"
                                            style="padding-top:0; padding-left:18px; padding-bottom:9px; padding-right:18px;">

                                            <a href="*|ARCHIVE|*" target="_blank">View this email in
                                                your browser</a>
                                        </td>
                                    </tr>
                                    </tbody>
                                </table>
                                <!--[if mso]>
                                </td>
                                <![endif]-->
                                <!--[if mso]>
                                </tr>
                                </table>
                                <![endif]-->
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>
        <!--[if gte mso 9]>
        </td>
        </tr>
        </table>
        <![endif]-->
    </td>
</tr>
<tr>
    <td align="center" valign="top" id="templateHeader">
        <!--[if gte mso 9]>
        <table align="center" border="0" cellspacing="0" cellpadding="0" width="600"
               style="width:600px;">
            <tr>
                <td align="center" valign="top" width="600" style="width:600px;">
        <![endif]-->
        <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%"
               class="templateContainer">
            <tr>
                <td valign="top" class="headerContainer">
                    <table border="0" cellpadding="0" cellspacing="0" width="100%"
                           class="mcnImageBlock" style="min-width:100%;">
                        <tbody class="mcnImageBlockOuter">
                        <tr>
                            <td valign="top" style="padding:9px" class="mcnImageBlockInner">
                                <table align="left" width="100%" border="0" cellpadding="0"
                                       cellspacing="0" class="mcnImageContentContainer"
                                       style="min-width:100%;">
                                    <tbody>
                                    <tr>
                                        <td class="mcnImageContent" valign="top"
                                            style="padding-right: 9px; padding-left: 9px; padding-top: 0; padding-bottom: 0; text-align:center;">


                                            <img align="center" alt=""
                                                 src="{{ mail_url("emails/images/logo.png") }}"
                                                 width="391"
                                                 style="max-width:391px; padding-bottom: 0; display: inline !important; vertical-align: bottom;"
                                                 class="mcnImage">
                                        </td>
                                    </tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>
        <!--[if gte mso 9]>
        </td>
        </tr>
        </table>
        <![endif]-->
    </td>
</tr>
<tr>
    <td align="center" valign="top" id="templateBody">
        <!--[if gte mso 9]>
        <table align="center" border="0" cellspacing="0" cellpadding="0" width="600"
               style="width:600px;">
            <tr>
                <td align="center" valign="top" width="600" style="width:600px;">
        <![endif]-->
        <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%"
               class="templateContainer">
            <tr>
                <td valign="top" class="bodyContainer"
                    style="padding-top:0;padding-left:18px;padding-bottom:9px;padding-right:18px">Please use the below
                    code to verify your email
                </td>
            </tr>
            <tr>
                <td valign="top" class="bodyContainer"
                    style="padding-top:0;padding-left:18px;padding-bottom:9px;padding-right:18px">code : {{$code}}</td>
            </tr>
            <tr>
                <td valign="top" class="bodyContainer"
                    style="padding-top:0;padding-left:18px;padding-bottom:9px;padding-right:18px">
                    <span style="font-size:12px">Confirm your email address to continue creating and sharing looks on Topshou.</span>
                </td>
            </tr>
        </table>
        <!--[if gte mso 9]>
        </td>
        </tr>
        </table>
        <![endif]-->
    </td>
</tr>
@include("emails.partials.footer")