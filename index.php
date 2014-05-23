<?php

include_once('includes/config.inc.php');
include_once('includes/session.inc.php');
include_once('includes/misc.inc.php');

if (isset($_GET['logout']) or isset($_POST['logout'])) {
    logout();
    header("Location: index.php");
}

if (!is_logged_in() and $_POST['formname'] == "loginform") {
    if (try_login() === TRUE) {
        set_logged_in($_POST['username']);
    } else {
        $errormsg = "Error while trying to authenticate you\n";
    }
}

?>
<html>
    <head>
        <link href="jquery-ui/themes/base/jquery.ui.all.css" rel="stylesheet" type="text/css" />
        <link href="jtable/themes/metro/lightgray/jtable.css" rel="stylesheet" type="text/css" />
        <link href="css/base.css" rel="stylesheet" type="text/css" />
        <script src="jquery-ui/jquery-1.10.2.js" type="text/javascript"></script>
	    <script src="jquery-ui/ui/jquery.ui.core.js" type="text/javascript"></script>
	    <script src="jquery-ui/ui/jquery.ui.dialog.js" type="text/javascript"></script>
        <script src="jtable/jquery.jtable.min.js" type="text/javascript"></script>
    </head>

<?
if (!is_logged_in()) {
    ?>
<body onload="document.getElementById('username').focus()">
<div class="loginblock">
  <div class="logo">
    <img src="https://www.tuxis.nl/uploads/images/nsedit.png" alt="Logo" />
  </div>
  <div class="login">
    <? if (isset($errormsg)) {
        echo '<span style="color: red">'.$errormsg.'</span><br />';
       }
    ?>
    <form action="index.php" method="post">
        <table>
            <tr>
                <td class="label">Gebruikersnaam:</td>
                <td><input id="username" type="text" name="username"/></td>
            </tr>
            <tr>
                <td class="label">Wachtwoord:</td>
                <td><input type="password" name="password"/></td>
            </tr>
            <tr>
				<td></td>
                <td><input type="submit" name="submit" value="Inloggen"/></td>
            </tr>
        </table>
        <input type="hidden" name="formname" value="loginform"/>
    </form>
  </div>
 </div>
</body>
</html>

<?
    exit(0);
}

if (is_adminuser()) {
    foreach (get_all_users() as $user) {
        $userlist[] = "'".$user['emailaddress']."':'".$user['emailaddress']."'";
    }

    $ulist = ',';
    $ulist .= join(',', $userlist);
}

foreach ($templates as $template) {
    if (is_adminuser() or (isset($template['owner']) && $template['owner'] == get_sess_user()) or ($template['owner'] == 'public')) {
        $templatelist[] = "'".$template['name']."':'".$template['name']."'";
    }
}

if (isset($templatelist)) {
    $tmpllist = ',';
    $tmpllist .= join(',', $templatelist);
}

?>
    <body>
        <div id="wrap">
            <div id="menu" class="jtable-main-container">
                <div class="jtable-title">
                    <div class="jtable-title-text">
                        Menu
                    </div>
                </div>
                <ul>
                    <li><a href="#" id="zoneadmin">Zones</a></li>
                    <? if (is_adminuser()) { ?>
                    <li><a href="#" id="useradmin">Users</a></li>
                    <? } ?>
                    <li><a href="index.php?logout=1">Logout</a></li>
                </ul>
            </div>
            <div id="zones">
                <div class="tables" id="MasterZones"></div>
                <div class="tables" id="SlaveZones"></div>
            </div>
            <? if (is_adminuser()) { ?>
            <div id="users">
                <div class="tables" id="Users"></div>
            </div>
            <? } ?>
        </div>
        <script type="text/javascript">
            $(document).ready(function () {
                <? if (is_adminuser()) { ?>
                $('#Users').hide();
                $('#useradmin').click(function () {
                    $('#Users').show();
                    $('#MasterZones').hide();
                    $('#SlaveZones').hide();
                });
                $('#zoneadmin').click(function () {
                    $('#Users').hide();
                    $('#MasterZones').show();
                    $('#SlaveZones').show();
                });
                $('#Users').jtable({
                    title: 'Users',
                    paging: true,
                    pageSize: 20,
                    sorting: false,
                    actions: {
                        listAction:   'users.php?action=list',
                        createAction: 'users.php?action=create',
                        deleteAction: 'users.php?action=delete',
                        updateAction: 'users.php?action=update'
                    },
                    messages: {
                        addNewRecord: 'Add new user',
                    },
                    fields: {
                        id: {
                            key: true,
                            type: 'hidden'
                        },
                        emailaddress: {
                            title: 'User',
                        },
                        password: {
                            title: 'Password',
                            type: 'password',
                            list: false,
                        },
                        isadmin: {
                            title: 'Admin',
                            type: 'checkbox',
                            values: { '0' : 'No', '1' : 'Yes' }
                        }
                    }
                });
                $('#Users').jtable('load');
                <? } ?>
                $('#SlaveZones').jtable({
                    title: 'Slave Zones',
                    paging: true,
                    pageSize: 20,
                    sorting: false,
                    messages: {
                        addNewRecord: 'Add new slave zone',
                        noDataAvailable: 'No slave zones configured',
                    },
                    openChildAsAccordion: true,
                    actions: {
                        listAction:   'zones.php?action=listslaves',
                        createAction: 'zones.php?action=create',
                        deleteAction: 'zones.php?action=delete'
                    },
                    fields: {
                        id: {
                            key: true,
                            type: 'hidden'
                        },
                        name: {
                            title: 'Domain',
                        },
                        <? if (is_adminuser()) { ?>
                        owner: {
                            title: 'Owner',
                            options: {'admin':'admin'<? echo $ulist; ?>},
                            defaultValue: 'admin',
                        },
                        <? } ?>
                        kind: {
                            create: true,
                            type: 'hidden',
                            list:   false,
                            defaultValue: 'Slave'
                        },
                        serial: {
                            title: 'Serial',
                            create: false,
                        },
                        records: {
                            width: '5%',
                            title: 'Records',
                            paging: true,
                            pageSize: 20,
                            edit: false,
                            create: false,
                            display: function(zone) {
                                var $img = $('<img class="list" src="jtable/themes/metro/list.png" title="Records" />');
                                $img.click(function() {
                                    $('#MasterZones').jtable('openChildTable',
                                    $img.closest('tr'), {
                                        title: 'Records in '+zone.record.name,
                                        openChildAsAccordion: true,
                                        actions: {
                                            listAction: 'zones.php?action=listrecords&zoneurl='+zone.record.url,
                                        },
                                        fields: {
                                            name: {
                                                title: 'Label',
                                            },
                                            type: {
                                                title: 'Type'
                                            },
                                            prio: {
                                                title: 'Prio'
                                            },
                                            content: {
                                                title: 'Content',
                                            },
                                            ttl: {
                                                title: 'TTL',
                                            },
                                        }
                                    }, function (data) {
                                                data.childTable.jtable('load');
                                    })
                                });
                                return $img;
                            }
                        },
                    }
                });
                $('#SlaveZones').jtable('load');
                $('#MasterZones').jtable({
                    title: 'Master/Native Zones',
                    paging: true,
                    pageSize: 20,
                    messages: {
                        addNewRecord: 'Add new zone',
                        noDataAvailable: 'No zones configured',
                    },
                    sorting: false,
                    openChildAsAccordion: true,
                    actions: {
                        listAction:   'zones.php?action=list',
                        createAction: 'zones.php?action=create',
                        deleteAction: 'zones.php?action=delete',
                        <? if (is_adminuser()) { ?>
                        updateAction: 'zones.php?action=update'
                        <? } ?>
                    },
                    fields: {
                        id: {
                            key: true,
                            type: 'hidden'
                        },
                        name: {
                            title: 'Domain',
                        },
                        <? if (is_adminuser()) { ?>
                        owner: {
                            title: 'Owner',
                            options: {'admin':'admin'<? echo $ulist; ?>},
                            defaultValue: 'admin',
                        },
                        <? } ?>
                        kind: {
                            title: 'Type',
                            options: { 'Native':'Native', 'Master':'Master'},
                            defaultValue: '<? echo $defaults['defaulttype']; ?>',
                            edit: false,
                        },
                        template: {
                            title: 'Template',
                            options: {'None':'None'<? echo $tmpllist; ?>},
                            list: false,
                            create: true,
                            edit: false,
                        },
                        nameserver1: {
                            title: 'Pri. Nameserver',
                            create: true,
                            list: false,
                            edit: false,
                            defaultValue: '<? echo $defaults['primaryns']; ?>',
                        },
                        nameserver2: {
                            title: 'Sec. Nameserver',
                            create: true,
                            list: false,
                            edit: false,
                            defaultValue: '<? echo $defaults['secondaryns']; ?>',
                        },
                        serial: {
                            title: 'Serial',
                            create: false,
                            edit: false,
                        },
                        records: {
                            width: '5%',
                            title: 'Records',
                            edit: false,
                            create: false,
                            display: function(zone) {
                                var $img = $('<img class="list" src="jtable/themes/metro/list.png" title="Records" />');
                                $img.click(function() {
                                    $('#MasterZones').jtable('openChildTable',
                                    $img.closest('tr'), {
                                        title: 'Records in '+zone.record.name,
                                        messages: {
                                            addNewRecord: 'Add to '+zone.record.name,
                                            noDataAvailable: 'No records for '+zone.record.name,
                                        },
                                        paging: true,
                                        pageSize: 20,
                                        openChildAsAccordion: true,
                                        actions: {
                                            listAction: 'zones.php?action=listrecords&zoneurl='+zone.record.url,
                                            createAction: 'zones.php?action=createrecord&zoneurl='+zone.record.url,
                                            deleteAction: 'zones.php?action=deleterecord&zoneurl='+zone.record.url,
                                            updateAction: 'zones.php?action=editrecord&zoneurl='+zone.record.url,
                                        },
                                        fields: {
                                            domid: {
                                                create: true,
                                                type: 'hidden',
                                                defaultValue: zone.record.id,
                                            },
                                            id: {
                                                key: true,
                                                create: false,
                                                edit: false,
                                                list: false,
                                            },
                                            domain: {
                                                create: true,
                                                type: 'hidden',
                                                defaultValue: zone.record.name,
                                            },
                                            name: {
                                                title: 'Label',
                                                create: true,
                                            },
                                            type: {
                                                options: {'AAAA':'AAAA','A':'A','CNAME':'CNAME','MX':'MX','SRV':'SRV','TXT':'TXT','NS':'NS','SOA':'SOA'},
                                                create: true,
                                            },
                                            priority: {
                                                title: 'Prio',
                                                create: true,
                                                defaultValue: '<? echo $defaults['priority']; ?>',
                                            },
                                            content: {
                                                title: 'Content',
                                                create: true,
                                            },
                                            ttl: {
                                                title: 'TTL',
                                                create: true,
                                                defaultValue: '<? echo $defaults['ttl']; ?>',
                                            },
                                        }
                                    }, function (data) {
                                                data.childTable.jtable('load');
                                    })
                                });
                                return $img;
                            }
                        },
                    }
                });
                $('#MasterZones').jtable('load');
            });
        </script>
    </body>
</html>

