<?php

include_once('includes/config.inc.php');
include_once('includes/session.inc.php');
include_once('includes/misc.inc.php');

if (isset($_GET['logout']) or isset($_POST['logout'])) {
    logout();
    header("Location: index.php");
    exit(0);
}

if (!is_logged_in() and isset($_POST['formname']) and $_POST['formname'] === "loginform") {
    if (!try_login()) {
        $errormsg = "Error while trying to authenticate you\n";
    }
}

?>
<html>
<head>
    <title>NSEdit!</title>
    <link href="jquery-ui/themes/base/jquery.ui.all.css" rel="stylesheet" type="text/css"/>
    <link href="jtable/lib/themes/metro/blue/jtable.min.css" rel="stylesheet" type="text/css"/>
    <link href="css/base.css" rel="stylesheet" type="text/css"/>
    <script src="jquery-ui/jquery-1.10.2.js" type="text/javascript"></script>
    <script src="jquery-ui/ui/jquery.ui.core.js" type="text/javascript"></script>
    <script src="jquery-ui/ui/jquery.ui.widget.js" type="text/javascript"></script>
    <script src="jquery-ui/ui/jquery.ui.mouse.js" type="text/javascript"></script>
    <script src="jquery-ui/ui/jquery.ui.draggable.js" type="text/javascript"></script>
    <script src="jquery-ui/ui/jquery.ui.position.js" type="text/javascript"></script>
    <script src="jquery-ui/ui/jquery.ui.button.js" type="text/javascript"></script>
    <script src="jquery-ui/ui/jquery.ui.resizable.js" type="text/javascript"></script>
    <script src="jquery-ui/ui/jquery.ui.dialog.js" type="text/javascript"></script>
    <script src="jtable/lib/jquery.jtable.min.js" type="text/javascript"></script>
    <script src="js/addclear/addclear.js" type="text/javascript"></script>
</head>

<?
if (!is_logged_in()) {
?>
<body onload="document.getElementById('username').focus()">
<div class="loginblock">
    <div class="logo">
        <img src="https://www.tuxis.nl/uploads/images/nsedit.png" alt="Logo"/>
    </div>
    <div class="login">
        <? if (isset($errormsg)) {
            echo '<span style="color: red">' . $errormsg . '</span><br />';
        }
        ?>
        <form action="index.php" method="post">
            <table>
                <tr>
                    <td class="label">Username:</td>
                    <td><input id="username" type="text" name="username"></td>
                </tr>
                <tr>
                    <td class="label">Password:</td>
                    <td><input type="password" name="password"></td>
                </tr>
                <?php
                if (isset($secret) && $secret) {
                ?>
                <tr>
                    <td class="label">Remember me:</td>
                    <td><input type="checkbox" name="autologin" value="1"></td>
                </tr>
                <?php
                }
                ?>
                <tr>
                    <td></td>
                    <td><input type="submit" name="submit" value="Log me in!"></td>
                </tr>
            </table>
            <input type="hidden" name="formname" value="loginform">
        </form>
    </div>
</div>
</body>
</html>

<?
exit(0);
}

?>
<body>
<div id="wrap">
    <div id="dnssecinfo">
    </div>
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
        <? if (is_adminuser() or $allowzoneadd === TRUE) { ?>
        <div style="visibility: hidden;" id="ImportZone"></div>
        <? } ?>
        <div class="tables" id="MasterZones">
            <div class="searchbar" id="searchbar">
                <input type="text" id="domsearch" name="domsearch" placeholder="Search...."/>
            </div>
        </div>
        <div class="tables" id="SlaveZones"></div>
    </div>
    <? if (is_adminuser()) { ?>
        <div id="users">
            <div class="tables" id="Users"></div>
        </div>
    <? } ?>
</div>
<script type="text/javascript">
window.csrf_token = '<?php echo CSRF_TOKEN ?>';

$(document).ready(function () {
    function csrfSafeMethod(method) {
        // these HTTP methods do not require CSRF protection
        return (/^(GET|HEAD|OPTIONS|TRACE)$/.test(method));
    }
    $.ajaxSetup({
        beforeSend: function(xhr, settings) {
            if (!csrfSafeMethod(settings.type) && !this.crossDomain) {
                xhr.setRequestHeader("X-CSRF-Token", window.csrf_token);
            }
        }
    });
});

function displayDnssecIcon(zone) {
    if (zone.record.dnssec == true) {
        var $img = $('<img class="list" src="img/lock.png" title="DNSSec Info" />');
        $img.click(function () {
            $("#dnssecinfo").html("");
            $.each(zone.record.keyinfo, function ( i, val) {
                if (val.dstxt) {
                    $("#dnssecinfo").append("<p><h2>"+val.keytype+"</h2><pre>"+val.dstxt+"</pre></p>");
                }
            });
            $("#dnssecinfo").dialog({
                modal: true,
                title: "DS-records for "+zone.record.name,
                width: 'auto',
                buttons: {
                    Ok: function() {
                        $( this ).dialog( "close" );
                    }
                }
            });
        });
        return $img;
    } else {
        return '<img src="img/lock_open.png" title="DNSSec Disabled" />';
    }
}

function displayContent(fieldName) {
    return function(data) {
        var value = data.record[fieldName];
        switch (fieldName) {
        case 'priority':
            value = (value === 0) ? '' : value;
            break;
        }
        return $('<span>').text(value);
    }
}

function getEpoch() {
    return Math.round(+new Date()/1000);
}

$(document).ready(function () {
    var $epoch = getEpoch();

    $('#SlaveZones').jtable({
        title: 'Slave Zones',
        paging: true,
        pageSize: 20,
        sorting: false,
        messages: {
            addNewRecord: 'Add new slave zone',
            noDataAvailable: 'No slave zones found'
        },
        openChildAsAccordion: true,
        actions: {
            listAction: 'zones.php?action=listslaves',
            updateAction: 'zones.php?action=update',
            <? if (is_adminuser() or $allowzoneadd === TRUE) { ?>
            createAction: 'zones.php?action=create',
            deleteAction: 'zones.php?action=delete',
            <? } ?>
        },
        fields: {
            id: {
                key: true,
                type: 'hidden'
            },
            name: {
                title: 'Domain',
                width: '8%',
                display: displayContent('name'),
                edit: false,
                inputClass: 'domain',
                listClass: 'domain'
            },
            dnssec: {
                title: 'DNSSEC',
                width: '3%',
                create: false,
                edit: false,
                display: displayDnssecIcon,
                listClass: 'dnssec'
            },
            <? if (is_adminuser()) { ?>
            owner: {
                title: 'Owner',
                width: '8%',
                display: displayContent('owner'),
                options: function(data) {
                    return 'users.php?action=listoptions&e='+$epoch;
                },
                defaultValue: 'admin',
                inputClass: 'owner',
                listClass: 'owner'
            },
            <? } ?>
            kind: {
                create: true,
                type: 'hidden',
                list: false,
                defaultValue: 'Slave'
            },
            masters: {
                title: 'Masters',
                width: '20%',
                display: function(data) {
                    return $('<span>').text(data.record.masters.join('\n'));
                },
                input: function(data) {
                    var elem = $('<input type="text" name="masters">');
                    if (data && data.record) {
                        elem.attr('value', data.record.masters.join(','));
                    }
                    return elem;
                },
                inputClass: 'masters',
                listClass: 'masters'
            },
            serial: {
                title: 'Serial',
                width: '10%',
                display: displayContent('serial'),
                create: false,
                edit: false,
                inputClass: 'serial',
                listClass: 'serial'
            },
            records: {
                width: '5%',
                title: 'Records',
                paging: true,
                pageSize: 20,
                edit: false,
                create: false,
                display: function (zone) {
                    var $img = $('<img class="list" src="img/list.png" title="Records" />');
                    $img.click(function () {
                        $('#SlaveZones').jtable('openChildTable',
                            $img.closest('tr'), {
                                title: 'Records in ' + zone.record.name,
                                openChildAsAccordion: true,
                                actions: {
                                    listAction: 'zones.php?action=listrecords&zoneurl=' + zone.record.url
                                },
                                fields: {
                                    name: {
                                        title: 'Label',
                                        width: '7%',
                                        display: displayContent('name'),
                                        listClass: 'name'
                                    },
                                    type: {
                                        title: 'Type',
                                        width: '2%',
                                        display: displayContent('type'),
                                        listClass: 'type'
                                    },
                                    priority: {
                                        title: 'Prio',
                                        width: '1%',
                                        display: displayContent('priority'),
                                        listClass: 'priority'
                                    },
                                    content: {
                                        title: 'Content',
                                        width: '30%',
                                        display: displayContent('content'),
                                        listClass: 'content'
                                    },
                                    ttl: {
                                        title: 'TTL',
                                        width: '2%',
                                        display: displayContent('ttl'),
                                        listClass: 'ttl'
                                    }
                                }
                            }, function (data) {
                                data.childTable.jtable('load');
                            })
                    });
                    return $img;
                }
            }
        }
    });
    $('#MasterZones').jtable({
        title: 'Master/Native Zones',
        paging: true,
        pageSize: 20,
        messages: {
            addNewRecord: 'Add new zone',
            noDataAvailable: 'No zones found'
        },
        toolbar: {
            hoverAnimation: true,
            hoverAnimationDuration: 60,
            hoverAnimationEasing: undefined,
            items: [{
                <? if (is_adminuser() or $allowzoneadd === TRUE) { ?>
                icon: 'jtable/lib/themes/metro/add.png',
                text: 'Import a new zone',
                click: function() {
                    $('#ImportZone').jtable('showCreateForm');
                }
                <? } ?>
                }],
        },
        sorting: false,
        openChildAsAccordion: true,
        actions: {
            listAction: 'zones.php?action=list',
            <? if (is_adminuser() or $allowzoneadd === TRUE) { ?>
            createAction: 'zones.php?action=create',
            deleteAction: 'zones.php?action=delete',
            <? } ?>
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
                width: '8%',
                display: displayContent('name'),
                edit: false,
                inputClass: 'domain',
                listClass: 'domain'
            },
            dnssec: {
                title: 'DNSSEC',
                width: '3%',
                create: false,
                edit: false,
                display: displayDnssecIcon,
                listClass: 'dnssec'
            },
            <? if (is_adminuser()) { ?>
            owner: {
                title: 'Owner',
                width: '8%',
                display: displayContent('owner'),
                options: function(data) {
                    return 'users.php?action=listoptions&e='+$epoch;
                },
                defaultValue: 'admin',
                inputClass: 'owner',
                listClass: 'owner'
            },
            <? } ?>
            kind: {
                title: 'Type',
                width: '20%',
                display: displayContent('kind'),
                options: {'Native': 'Native', 'Master': 'Master'},
                defaultValue: '<? echo $defaults['defaulttype']; ?>',
                edit: false,
                inputClass: 'kind',
                listClass: 'kind'
            },
            template: {
                title: 'Template',
                options: <? echo json_encode(user_template_names()); ?>,
                list: false,
                create: true,
                edit: false,
                inputClass: 'template'
            },
            nameserver1: {
                title: 'Pri. Nameserver',
                create: true,
                list: false,
                edit: false,
                defaultValue: '<? echo $defaults['primaryns']; ?>',
                inputClass: 'nameserver nameserver1'
            },
            nameserver2: {
                title: 'Sec. Nameserver',
                create: true,
                list: false,
                edit: false,
                defaultValue: '<? echo $defaults['secondaryns']; ?>',
                inputClass: 'nameserver nameserver2'
            },
            serial: {
                title: 'Serial',
                width: '10%',
                display: displayContent('serial'),
                create: false,
                edit: false,
                inputClass: 'serial',
                listClass: 'serial'
            },
            records: {
                width: '5%',
                title: 'Records',
                edit: false,
                create: false,
                display: function (zone) {
                    var $img = $('<img class="list" src="img/list.png" title="Records" />');
                    $img.click(function () {
                        $('#MasterZones').jtable('openChildTable',
                            $img.closest('tr'), {
                                title: 'Records in ' + zone.record.name,
                                messages: {
                                    addNewRecord: 'Add to ' + zone.record.name,
                                    noDataAvailable: 'No records for ' + zone.record.name
                                },
                                paging: true,
                                pageSize: 20,
                                openChildAsAccordion: true,
                                actions: {
                                    listAction: 'zones.php?action=listrecords&zoneurl=' + zone.record.url,
                                    createAction: 'zones.php?action=createrecord&zoneurl=' + zone.record.url,
                                    deleteAction: 'zones.php?action=deleterecord&zoneurl=' + zone.record.url,
                                    updateAction: 'zones.php?action=editrecord&zoneurl=' + zone.record.url
                                },
                                fields: {
                                    domid: {
                                        create: true,
                                        type: 'hidden',
                                        defaultValue: zone.record.id
                                    },
                                    id: {
                                        key: true,
                                        type: 'hidden',
                                        create: false,
                                        edit: false,
                                        list: false
                                    },
                                    domain: {
                                        create: true,
                                        type: 'hidden',
                                        defaultValue: zone.record.name
                                    },
                                    name: {
                                        title: 'Label',
                                        width: '7%',
                                        create: true,
                                        display: displayContent('name'),
                                        inputClass: 'name',
                                        listClass: 'name'
                                    },
                                    type: {
                                        title: 'Type',
                                        width: '2%',
                                        options: function() {
/*
                                            zonename = new String(zone.record.name);
                                            if (zonename.match(/(\.in-addr|\.ip6)\.arpa/)) {
                                                return {
                                                    'PTR':'PTR',
                                                    'NS':'NS',
                                                    'MX':'MX',
                                                    'TXT':'TXT',
                                                    'SOA':'SOA'
                                                };
                                            }
*/
                                            return {
                                                'A': 'A',
                                                'AAAA': 'AAAA',
                                                'CNAME': 'CNAME',
                                                'MX': 'MX',
                                                'NAPTR': 'NAPTR',
                                                'NS': 'NS',
                                                'PTR': 'PTR',
                                                'SOA': 'SOA',
                                                'SPF': 'SPF',
                                                'SRV': 'SRV',
                                                'TLSA': 'TLSA',
                                                'TXT': 'TXT',
                                            };
                                        },
                                        display: displayContent('type'),
                                        create: true,
                                        inputClass: 'type',
                                        listClass: 'type'
                                    },
                                    priority: {
                                        title: 'Prio',
                                        width: '1%',
                                        create: true,
                                        display: displayContent('priority'),
                                        defaultValue: '<? echo $defaults['priority']; ?>',
                                        inputClass: 'priority',
                                        listClass: 'priority'
                                    },
                                    content: {
                                        title: 'Content',
                                        width: '30%',
                                        create: true,
                                        display: displayContent('content'),
                                        inputClass: 'content',
                                        listClass: 'content'
                                    },
                                    ttl: {
                                        title: 'TTL',
                                        width: '2%',
                                        create: true,
                                        display: displayContent('ttl'),
                                        defaultValue: '<? echo $defaults['ttl']; ?>',
                                        inputClass: 'ttl',
                                        listClass: 'ttl'
                                    }
                                }
                            }, function (data) {
                                data.childTable.jtable('load');
                            })
                    });
                    return $img;
                }
            }
        }
    });
    $('#ImportZone').jtable({
        title: 'Import zone',
        actions: {
            createAction: 'zones.php?action=create'
        },
        fields: {
            id: {
                key: true,
                type: 'hidden'
            },
            name: {
                title: 'Domain',
                inputClass: 'domain'
            },
            <? if (is_adminuser()) { ?>
            owner: {
                title: 'Owner',
                options: function(data) {
                    return 'users.php?action=listoptions&e='+$epoch;
                },
                defaultValue: 'admin',
                inputClass: 'owner'
            },
            <? } ?>
            kind: {
                title: 'Type',
                options: {'Native': 'Native', 'Master': 'Master'},
                defaultValue: '<? echo $defaults['defaulttype']; ?>',
                edit: false,
                inputClass: 'type'
            },
            zone: {
                title: 'Zonedata',
                type: 'textarea',
                inputClass: 'zonedata'
            },
            owns: {
                title: 'Overwrite Nameservers',
                type: 'checkbox',
                values: {'0': 'No', '1': 'Yes'},
                defaultValue: 1,
                inputClass: 'overwrite_namerserver'
            },
            nameserver1: {
                title: 'Pri. Nameserver',
                create: true,
                list: false,
                edit: false,
                defaultValue: '<? echo $defaults['primaryns']; ?>',
                inputClass: 'nameserver nameserver1'
            },
            nameserver2: {
                title: 'Sec. Nameserver',
                create: true,
                list: false,
                edit: false,
                defaultValue: '<? echo $defaults['secondaryns']; ?>',
                inputClass: 'nameserver nameserver2'
            },
        },
        recordAdded: function() {
            $("#MasterZones").jtable('load');
            $("#SlaveZones").jtable('load');
        }

    });
    $('#domsearch').addClear({
        onClear: function() { $('#MasterZones').jtable('load'); }
    });

    function searchDoms() {
        $('#MasterZones').jtable('load', {
            domsearch: $('#domsearch').val()
        });
        $('#SlaveZones').jtable('load', {
            domsearch: $('#domsearch').val()
        });
    }

    stimer = 0;

    $('#domsearch').on('input', function (e) {
        e.preventDefault();
        clearTimeout(stimer);
        stimer = setTimeout(searchDoms, 400);
    });

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
            listAction: 'users.php?action=list',
            createAction: 'users.php?action=create',
            deleteAction: 'users.php?action=delete',
            updateAction: 'users.php?action=update'
        },
        messages: {
            addNewRecord: 'Add new user'
        },
        fields: {
            id: {
                key: true,
                type: 'hidden'
            },
            emailaddress: {
                title: 'User',
                display: displayContent('emailaddress'),
                inputClass: 'emailaddress',
                listClass: 'emailaddress'
            },
            password: {
                title: 'Password',
                type: 'password',
                list: false,
                inputClass: 'password',
            },
            isadmin: {
                title: 'Admin',
                type: 'checkbox',
                values: {'0': 'No', '1': 'Yes'},
                inputClass: 'isadmin',
                listClass: 'isadmin'
            }
        },
        recordAdded: function() {
            $epoch = getEpoch();
            $("#MasterZones").jtable('reload');
            $("#SlaveZones").jtable('reload');
        }
    });
    $('#Users').jtable('load');
    <? } ?>
    $('#MasterZones').jtable('load');
    $('#SlaveZones').jtable('load');
});
</script>
</body>
</html>
