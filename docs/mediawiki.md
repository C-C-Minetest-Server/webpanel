## Recommended configuration

## WebPanel PHP

WebPanel `config.php`:

```php
// 5. MediaWiki Integration

// URL to MediaWiki API
// e.g. https://wiki.example.com/w/api.php
$emoWebPanelMWAPI = 'https://wiki-twi.1f616emo.xyz/api.php';

// Username of the in-game privileges worker
$emoWebPanelMWName = 'In-game Privieges Worker';

// Bot Password of the in-game privileges worker
$emoWebPanelMWBotPassword = 'worker@REDACTED';

// List of privileges to be synced by the worker
$emoWebPanelMWSyncPrivs = [ 'server', 'ban', 'role_helper'] ;
```

### MediaWiki

MediaWiki `LocalSettings.php`:

```php
/* Keep these bottom */
/* User rights manipulation - WebPanel worker */

// Prevent bureaucrats from modifying in-game privileges
$wgGroupPermissions['bureaucrat']['userrights'] = false;
$wgGroupsAddToSelf['bureaucrat'] = false;
$wgGroupsRemoveFromSelf['bureaucrat'] = false;

// Copy bureaucrat to ingame-server
$wgGroupPermissions['ingame-server'] = $wgGroupPermissions['bureaucrat'];
$wgRevokePermissions['ingame-server'] = $wgRevokePermissions['bureaucrat'];
$wgGroupsAddToSelf['ingame-server'] = $wgGroupsAddToSelf['bureaucrat'];
$wgGroupsRemoveFromSelf['ingame-server'] = $wgGroupsRemoveFromSelf['bureaucrat'];

// Copy sysop to ingame-ban
$wgGroupPermissions['ingame-ban'] = $wgGroupPermissions['sysop'];
$wgRevokePermissions['ingame-ban'] = $wgRevokePermissions['sysop'];
$wgGroupsAddToSelf['ingame-ban'] = $wgGroupsAddToSelf['sysop'];
$wgGroupsRemoveFromSelf['ingame-ban'] = $wgGroupsRemoveFromSelf['sysop'];

// You can also do the same on custom privileges
$wgGroupPermissions['ingame-role_helper'] = array(
        'patrol' => true,
        'autopatrol' => true,
);

// Add the privilege worker into this group (and bot group)
$wgGroupPermissions['ingame-privs-worker'] = array();

// Distribute right to grant privs to bureaucrats and ingame-privs-worker
foreach (array_keys($wgGroupPermissions) as $ug) {
        if (in_array($ug, [ '*', 'user', 'autoconfirmed' ])) continue;
        if (str_starts_with($ug, 'ingame-') && $ug != 'ingame-privs-worker') {
                $wgAddGroups['ingame-privs-worker'][] = $ug;
                $wgRemoveGroups['ingame-privs-worker'][] = $ug;
        } else {
                $wgAddGroups['bureaucrat'][] = $ug;
                $wgRemoveGroups['bureaucrat'][] = $ug;
        }
}

// Apply $wgAddGroups to in-game synced privieges
$wgAddGroups['ingame-ban'] = $wgAddGroups['sysop'];
$wgAddGroups['ingame-server'] = $wgAddGroups['bureaucrat'];
```

MediaWiki edit tags:

* `webpanel-ingame-privs-sync`

MediaWiki messages:

| Page Name | Contents |
|---|---|
| MediaWiki:Group-ingame-ban | Moderators |
| MediaWiki:Group-ingame-ban-member | Moderator |
| MediaWiki:Group-ingame-server | Server owners[^1] |
| MediaWiki:Group-ingame-server-member | Server owner |
| MediaWiki:Group-ingame-privs-worker | In-game privileges worker[^2] |
| MediaWiki:Group-ingame-privs-worker-member | In-game privileges worker |
| MediaWiki:Tag-webpanel-ingame-privs-sync | \[[User:<username>\|In-game privileges sync]][^3] |
| Mediawiki:Tag-webpanel-ingame-privs-sync-description | \[[User:<username>\|In-game privileges sync]][^3]

[^1]: Per MediaWiki spec, this should be plural. This can be either singular or plural depending on the situation of your server.
[^2]: Based on the fact that one server would have only one in-game privileges worker, it is fine to set it singular.
[^3]: This should point to the user page of the worker bot user.
