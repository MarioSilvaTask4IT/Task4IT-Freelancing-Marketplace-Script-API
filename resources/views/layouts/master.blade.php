<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
 <head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <title>Task4IT Email</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
</head>

<body style="margin: 0; padding: 0;  font-family: Tahoma;">

<!-- header-->

<TABLE cellspacing="0" cellpadding="0" width="700" align="center" border=0>

    <TR HEIGHT=75>
        <TD width="166" bgcolor="#00a2ff" height="75" >
            <img src="https://s3-eu-west-1.amazonaws.com/static.task4it/email/logo-email.jpg" alt="logo Task4it" align="left"  height=72 width=166></img>
        </TD>

        <TD width="375" align=right bgcolor="#00a2ff" height="75" >
            <img src="https://s3-eu-west-1.amazonaws.com/static.task4it/email/telefone-ico-email.jpg" alt="contacto task4it" style="padding-top: 14px;" >

        </TD>

        <TD bgcolor="#00a2ff" height="75" width="159"  >

            <font color="#ffffff"><p style="padding-left:7px;padding-right:10px">Email us user@user.com</font></p>
        </TD>

    </TR>
</TABLE>

<!-- sombra header-->

<TABLE cellspacing="0" cellpadding="0" width="700" align="center" border=0>
    <TR height="3" >
        <td colspan = "2" bgcolor="#0067ff"></TD>
    </TR>

</TABLE>

    <!-- area numero e data newsletter-->
<TABLE cellspacing="0" cellpadding="0" width="700" align="center" border=0>
    <TR HEIGHT=97>
        <TD>
            <font color="#0067ff"><strong>&nbsp;&nbsp;&nbsp;&nbsp;@yield('title')</strong></font>
        </TD>

        <TD align=right>
            <font color="#0067ff">@yield('date')</font>
        </TD>
    </TR>
</TABLE>

@yield('content')

<!-- MARGIN TOP -->
<TABLE cellspacing="0" cellpadding="0" width="700" align="center" border=0>
    <TR BORDER=0>
        <TD colspan = "2" bgcolor=#FFFFFF height="20"></TD>
    </TR>
</TABLE>

<!-- MARGIN TOP -->
<TABLE cellspacing="0" cellpadding="0" width="700" align="center" border=0>
    <TR BORDER=0>
        <TD colspan = "2" bgcolor=#FFFFFF height="20"></TD>
    </TR>
</TABLE>

<!-- fOOTER -->
<TABLE cellspacing="0" cellpadding="0" width="700" align="center" border=0>

    <TR BORDER=0>
        <TD colspan = "2" bgcolor=#f1f1f1 height="100">
            <p style="padding-top: 10px;padding-left:43px;"><font color="#0067ff"><STRONG>&copy;&nbsp;task4it {{ date('Y') }} - All rights reserved.</STRONG></font></p>

        </TD>
    </TR>

</TABLE>

</body>

</html>
