<?php // guilds.php :: All guild/clan functions.

// Before allowing anything else, we make sure the person is actually in town.
global $townrow;
if ($townrow == false) { die(header("Location: index.php")); }

function guildmain() {
    
    global $userrow;
    
    if ($userrow["guild"] != 0) {
        if (!isset($_GET["list"])) { guildhome(); }
    }
    
    $guilds = doquery("SELECT * FROM {{table}} WHERE isactive='1' ORDER BY name", "guilds");
    $row["guildlist"] = "<table style=\"width: 95%;\" cellspacing=\"0\" cellpadding=\"0\">";
    $bgcolor = "background-color: #ffffff;";
    if (mysql_num_rows($guilds) > 0) { 
        while ($guildrow = mysql_fetch_array($guilds)) {
            $row["guildlist"] .= "<tr><td style=\"$bgcolor padding: 3px;\">[<span style=\"color: ".$guildrow["color1"].";\"><b>".$guildrow["tagline"]."</b></span>] <span style=\"color: ".$guildrow["color2"].";\"><b>".$guildrow["name"]."</b></span></td><td style=\"$bgcolor padding: 3px; text-align: right;\"><a href=\"index.php?do=guildapp&id=".$guildrow["id"]."\">Apply to Join</a> | <a href=\"index.php?do=guildmembers&id=".$guildrow["id"]."\">Member List</a></td></tr>\n";
            if ($bgcolor == "background-color: #ffffff;") { $bgcolor = "background-color: #dddddd;"; } else { $bgcolor = "background-color: #ffffff;"; }
        }
    } else {
        $row["guildlist"] .= "<tr><td>No Guilds have been created yet.</td></tr>";
    }
    
    $row["guildlist"] .= "</table><br />";
    display("Guild Hall", parsetemplate(gettemplate("guild_list"), $row));
    
}

function guildhome() {
    
    global $userrow;
    
    if ($userrow["guild"] == 0) { err("You are not yet a member of any Guild. Please <a href=\"index.php\">go back</a> and try again."); }
    $guild = dorow(doquery("SELECT * FROM {{table}} WHERE id='".$userrow["guild"]."' LIMIT 1", "guilds"));
    
    switch($userrow["guildrank"]) {
        case 1: $template = "guild_homelow"; break;
        case 2: $template = "guild_homelow"; break;
        case 3: $template = "guild_homelow"; break;
        case 4: $template = "guild_homemid"; break;
        case 5: $template = "guild_homehigh"; break;
        default: $template = "guild_homelow"; break;
    }
    
    // Setup Babblebox.
    $pagerow["babblebox"] = "<div class=\"big\"><b>Guild Babblebox</b></div>\n<iframe src=\"index.php?do=babblebox&g=".$userrow["guild"]."\" name=\"sbox\" width=\"100%\" height=\"200\" frameborder=\"0\" id=\"bbox\">Your browser does not support inline frames! The Babble Box will not be available until you upgrade to a newer <a href=\"http://www.mozilla.org\" target=\"_new\">browser</a>.</iframe>";
    
    // Setup Bank.
    $pagerow["bank"] = number_format($guild["bank"]);
    
    // Pull memberslist for select box.
    $members = doquery("SELECT * FROM {{table}} WHERE guild='".$userrow["guild"]."' ORDER BY guildrank", "users");
    $pagerow["memberselect"] = "<select name=\"charid\" style=\"font: 10px Arial;\">";
    while ($memrow = mysql_fetch_array($members)) {
        $pagerow["memberselect"] .= "<option value=\"".$memrow["id"]."\">".$memrow["charname"]." (Rank ".$memrow["guildrank"].")</option>\n";
    }
    $pagerow["memberselect"] .= "</select>";
    
    // Pull applications for selectbox.
    $apps = doquery("SELECT * FROM {{table}} WHERE guild='".$userrow["guild"]."' ORDER BY id", "guildapps");
    if (mysql_num_rows($apps) > 0) { 
        $pagerow["appselect"] = "<select name=\"charid\" style=\"font: 10px Arial;\">";
        while ($approw = mysql_fetch_array($apps)) {
            $pagerow["appselect"] .= "<option value=\"".$approw["charid"]."\">".$approw["charname"]."</option>\n";
        }
        $pagerow["appselect"] .= "</select><br /><input type=\"submit\" name=\"approve\" value=\"Approve\" /> <input type=\"submit\" name=\"deny\" value=\"Deny\" />";
    } else {
        $pagerow["appselect"] = "No new applications.";
    }
    
    // Set up everything else.
    if (trim($guild["news"]) != "") {
        $pagerow["news"] = nl2br($guild["news"]);
    } else { $pagerow["news"] = "No news yet."; }
    
    $title = "[".$guild["tagline"]."] ".$guild["name"];
    display($title, parsetemplate(gettemplate($template),$pagerow));
    
}

function guildcreate() {
    
    global $controlrow, $userrow;
    
    // Errors.
    if ($userrow["gold"] < $controlrow["guildstartup"]) { err("You do not have enough gold to create a Guild. Starting your own Guild requires ".number_format($controlrow["guildstartup"])." gold. Please <a href=\"index.php\">go back</a> and try again."); }
    if ($userrow["guild"] != 0) { err("You are already a member of another Guild. You must renounce your current membership before starting your own Guild. Please <a href=\"index.php\">go back</a> and try again."); }
    $appquery = doquery("SELECT * FROM {{table}} WHERE charid='".$userrow["id"]."' LIMIT 1", "guildapps");
    if (mysql_num_rows($appquery) != 0) { err("You have already applied to join another Guild. Please <a href=\"index.php\">go back</a> and try again."); }
    
    if (isset($_POST["submit"])) {
        
        extract($_POST);
        
        // Errors.
        $errors = 0; $errorlist = "";
        if (preg_match("/[^A-z\ 0-9_\-]/", $name)==1) { $errors++; $errorlist .= "Guild names can only contain letters, numbers, spaces and hyphens.<br />"; } // Thanks to "Carlos Pires" from php.net!
        if (preg_match("/[^A-z\ 0-9_\-]/", $rank1)==1) { $errors++; $errorlist .= "Rank 1 can only contain letters, numbers, spaces and hyphens.<br />"; } // Thanks to "Carlos Pires" from php.net!
        if (preg_match("/[^A-z\ 0-9_\-]/", $rank2)==1) { $errors++; $errorlist .= "Rank 2 can only contain letters, numbers, spaces and hyphens.<br />"; } // Thanks to "Carlos Pires" from php.net!
        if (preg_match("/[^A-z\ 0-9_\-]/", $rank3)==1) { $errors++; $errorlist .= "Rank 3 can only contain letters, numbers, spaces and hyphens.<br />"; } // Thanks to "Carlos Pires" from php.net!
        if (preg_match("/[^A-z\ 0-9_\-]/", $rank4)==1) { $errors++; $errorlist .= "Rank 4 can only contain letters, numbers, spaces and hyphens.<br />"; } // Thanks to "Carlos Pires" from php.net!
        if (preg_match("/[^A-z\ 0-9_\-]/", $rank5)==1) { $errors++; $errorlist .= "Rank 5 can only contain letters, numbers, spaces and hyphens.<br />"; } // Thanks to "Carlos Pires" from php.net!
        if (preg_match("/[^A-z0-9_\-]/", $tagline)==1) { $errors++; $errorlist .= "Guild taglines must be alphanumeric.<br />"; } // Thanks to "Carlos Pires" from php.net!
        if (trim($name) == "") { $errors++; $errorlist .= "Guild name is required.<br />"; }
        if (trim($tagline) == "") { $errors++; $errorlist .= "Tagline is required.<br />"; }
        if (trim($color1) == "#") { $errors++; $errorlist .= "Tagline color is required.<br />"; }
        if (strlen($color1) < 7) { $errors++; $errorlist .= "Tagline color must be 7 characters long.<br />"; }
        if (trim($color2) == "#") { $errors++; $errorlist .= "Name color is required.<br />"; }
        if (strlen($color2) < 7) { $errors++; $errorlist .= "Name color must be 7 characters long.<br />"; }
        if (trim($joincost) == "") { $errors++; $errorlist .= "Cost to join is required.<br />"; }
        if (!is_numeric($joincost)) { $errors++; $errorlist .= "Cost to join must be a number.<br />"; }
        if (trim($rank1) == "") { $errors++; $errorlist .= "Rank 1 is required.<br />"; }
        if (trim($rank2) == "") { $errors++; $errorlist .= "Rank 2 is required.<br />"; }
        if (trim($rank3) == "") { $errors++; $errorlist .= "Rank 3 is required.<br />"; }
        if (trim($rank4) == "") { $errors++; $errorlist .= "Rank 4 is required.<br />"; }
        if (trim($rank5) == "") { $errors++; $errorlist .= "Rank 5 is required.<br />"; }
        
        // Should be fine. Go on and create it.
        if ($errors == 0) { 
            $querystring = "";
            unset($_POST["submit"]);
            foreach($_POST as $a => $b) {
                $querystring .= "$a='$b',";
            }
            $querystring .= "id='',isactive='1',founder='".$userrow["id"]."', members='1'";
            $query = doquery("INSERT INTO {{table}} SET $querystring", "guilds");

            // Now update the Founder's userrow.
            $query = doquery("UPDATE {{table}} SET gold=gold-".$controlrow["guildstartup"].", guild='".mysql_insert_id()."',guildrank='5',guildtag='$tagline',tagcolor='$color1',namecolor='$color2' WHERE id='".$userrow["id"]."' LIMIT 1", "users");
            
            // And we're done.
            display("Create a Guild", "Your guild was successfully created.<br /><br />You may now return to <a href=\"index.php\">the game</a>.");
            
        } else {
            
            // Die gracefully on errors.
            err("The following error(s) occurred when your account was being made:<br /><span style=\"color:red;\">$errorlist</span><br />Please <a href=\"users.php?do=register\">go back</a> and try again.");
            
        }
        
    }
    
    $row["guildstartup"] = number_format($controlrow["guildstartup"]);
    display("Create a Guild", parsetemplate(gettemplate("guild_create"), $row));
    
}

function guildedit() {
    
    global $userrow;
    
    $guild = dorow(doquery("SELECT * FROM {{table}} WHERE id='".$userrow["guild"]."' LIMIT 1", "guilds"));
    
    // Errors.
    if ($userrow["guildrank"] < 5) { err("You do not have permission to edit the Guild settings. Please <a href=\"index.php\">go back</a> and try again."); }
    
    if (isset($_POST["submit"])) {
        
        extract($_POST);
        
        // Errors.
        $errors = 0; $errorlist = "";
        if (preg_match("/[^A-z\ 0-9_\-]/", $rank1)==1) { $errors++; $errorlist .= "Rank 1 can only contain letters, numbers, spaces and hyphens.<br />"; } // Thanks to "Carlos Pires" from php.net!
        if (preg_match("/[^A-z\ 0-9_\-]/", $rank2)==1) { $errors++; $errorlist .= "Rank 2 can only contain letters, numbers, spaces and hyphens.<br />"; } // Thanks to "Carlos Pires" from php.net!
        if (preg_match("/[^A-z\ 0-9_\-]/", $rank3)==1) { $errors++; $errorlist .= "Rank 3 can only contain letters, numbers, spaces and hyphens.<br />"; } // Thanks to "Carlos Pires" from php.net!
        if (preg_match("/[^A-z\ 0-9_\-]/", $rank4)==1) { $errors++; $errorlist .= "Rank 4 can only contain letters, numbers, spaces and hyphens.<br />"; } // Thanks to "Carlos Pires" from php.net!
        if (preg_match("/[^A-z\ 0-9_\-]/", $rank5)==1) { $errors++; $errorlist .= "Rank 5 can only contain letters, numbers, spaces and hyphens.<br />"; } // Thanks to "Carlos Pires" from php.net!
        //if (preg_match("/#[a-fA-F0-9]/", $color1)==1) { $errors++; $errorlist .= "Tagline color does not appear to be a valid HTML color code.<br />"; }
        //if (preg_match("/#[a-fA-F0-9]/", $color2)==1) { $errors++; $errorlist .= "Name color does not appear to be a valid HTML color code.<br />"; }
        if (trim($color1) == "#") { $errors++; $errorlist .= "Tagline color is required.<br />"; }
        if (strlen($color1) != 7) { $errors++; $errorlist .= "Tagline color must be 7 characters long.<br />"; }
        if (trim($color2) == "#") { $errors++; $errorlist .= "Name color is required.<br />"; }
        if (strlen($color2) != 7) { $errors++; $errorlist .= "Name color must be 7 characters long.<br />"; }
        if (trim($joincost) == "") { $errors++; $errorlist .= "Cost to join is required.<br />"; }
        if (!is_numeric($joincost)) { $errors++; $errorlist .= "Cost to join must be a number.<br />"; }
        if (trim($rank1) == "") { $errors++; $errorlist .= "Rank 1 is required.<br />"; }
        if (trim($rank2) == "") { $errors++; $errorlist .= "Rank 2 is required.<br />"; }
        if (trim($rank3) == "") { $errors++; $errorlist .= "Rank 3 is required.<br />"; }
        if (trim($rank4) == "") { $errors++; $errorlist .= "Rank 4 is required.<br />"; }
        if (trim($rank5) == "") { $errors++; $errorlist .= "Rank 5 is required.<br />"; }
        
        // Should be fine. Go on and create it.
        if ($errors == 0) { 
            $querystring = "";
            unset($_POST["submit"]);
            foreach($_POST as $a => $b) {
                $querystring .= "$a='$b',";
            }
            $querystring .= "id=id";
            $query = doquery("UPDATE {{table}} SET $querystring WHERE id='".$guild["id"]."'", "guilds");
            $updatemem = doquery("UPDATE {{table}} SET namecolor='$color2', tagcolor='$color1' WHERE guild='".$guild["id"]."'", "users");

            // And we're done.
            display("Edit Guild", "Your guild was successfully edited.<br /><br />You may now return to <a href=\"index.php\">town</a> or to your <a href=\"index.php?do=guildhome\">Guild Hall</a>.");
            
        } else {
            
            // Die gracefully on errors.
            err("The following error(s) occurred when your account was being made:<br /><span style=\"color:red;\">$errorlist</span><br />Please <a href=\"users.php?do=register\">go back</a> and try again.");
            
        }
        
    }
    
    display("Edit Guild", parsetemplate(gettemplate("guild_edit"), $guild));
    
}

function guildapp() {
    
    global $userrow;
    
    $id = $_GET["id"];
    if (!is_numeric($id)) { err("Invalid input. Please <a href=\"index.php\">go back</a> and try again."); }
    $guild = dorow(doquery("SELECT * FROM {{table}} WHERE id='$id' LIMIT 1", "guilds"));
    if ($guild == false) { err("Invalid input. Please <a href=\"index.php\">go back</a> and try again."); }
    
    // Errors.
    if ($userrow["gold"] < $guild["joincost"]) { err("You do not have enough gold to join this Guild. Joining this Guild requires ".number_format($guild["joincost"])." gold. Please <a href=\"index.php\">go back</a> and try again."); }
    if ($userrow["guild"] != 0) { err("You are already a member of another Guild. You must renounce your current membership before joining this Guild. Please <a href=\"index.php\">go back</a> and try again."); }
    $appquery = doquery("SELECT * FROM {{table}} WHERE charid='".$userrow["id"]."' LIMIT 1", "guildapps");
    if (mysql_num_rows($appquery) != 0) { err("You have already applied to join another Guild. Please <a href=\"index.php\">go back</a> and try again."); }
    
    if (isset($_POST["yes"])) {
        
        $query = doquery("INSERT INTO {{table}} SET id='',guild='$id',charid='".$userrow["id"]."',charname='".$userrow["charname"]."'", "guildapps");
        $update = doquery("UPDATE {{table}} SET bank=bank+".$guild["joincost"]." WHERE id='".$guild["id"]."' LIMIT 1", "guilds");
        $updatemem = doquery("UPDATE {{table}} SET gold=gold-".$guild["joincost"]." WHERE id='".$userrow["id"]."' LIMIT 1", "users");
        $send = doquery("INSERT INTO {{table}} SET id='', postdate=NOW(), senderid='0', sendername='".$guild["name"]."', recipientid='".$guild["founder"]."', recipientname='Guild Leader', status='0', title='New Guild Application', message='Someone has applied to join your Guild.<br /><br /><b>Do not reply to this message!</b>', gold='0'", "messages");
        display("Join a Guild", "Thank you for applying to this Guild. If the Guild Leader approves your application, you will be notified via the Post Office.<br /><br />You may now return to <a href=\"index.php\">the game</a>.");
        
    } elseif (isset($_POST["no"])) {
        
        die(header("Location: index.php?do=guilds"));
        
    } else {
    
        $guild["joincost"] = number_format($guild["joincost"]);
        $guild["statement"] = nl2br($guild["statement"]);
        display("Join a Guild", parsetemplate(gettemplate("guild_apply"), $guild));
        
    }
    
}

function guildmembers() {
    
    $id = $_GET["id"];
    if (!is_numeric($id)) { err("Invalid input. Please <a href=\"index.php\">go back</a> and try again."); }
    $guild = dorow(doquery("SELECT * FROM {{table}} WHERE id='$id' LIMIT 1", "guilds"));
    if ($guild == false) { err("Invalid input. Please <a href=\"index.php\">go back</a> and try again."); }
    
    $query = doquery("SELECT * FROM {{table}} WHERE guild='$id' ORDER BY guildrank DESC", "users");
    $row["guildmembers"] = "<table style=\"width: 95%;\" cellspacing=\"0\" cellpadding=\"0\"><tr><td style=\"background-color: #dddddd; padding: 3px;\"><b>Name</b></td><td style=\"background-color: #dddddd; padding: 3px; text-align: right;\"><b>Rank</b></td></tr>\n";
    $bgcolor = "background-color: #ffffff;";
    if (mysql_num_rows($query) > 0) { 
        while ($guildrow = mysql_fetch_array($query)) {
            $row["guildmembers"] .= "<tr><td style=\"$bgcolor padding: 3px;\">[<span style=\"color: ".$guild["color1"].";\"><b>".$guild["tagline"]."</b></span>]<span style=\"color: ".$guild["color2"].";\"><b>".$guildrow["charname"]."</b></span></td><td style=\"$bgcolor padding: 3px; text-align: right;\">".$guild["rank".$guildrow["guildrank"]]."</td></tr>\n";
            if ($bgcolor == "background-color: #ffffff;") { $bgcolor = "background-color: #dddddd;"; } else { $bgcolor = "background-color: #ffffff;"; }
        }
    } else {
        $row["guildmembers"] .= "<tr><td>This Guild has no members yet.</td></tr>";
    }
    $row["guildmembers"] .= "</table><br />";
    $row["name"] = $guild["name"];
    display("Guild Hall", parsetemplate(gettemplate("guild_members"), $row));
    
}

function guildbank() {
    
    global $userrow;
    extract($_POST);
    
    $guild = dorow(doquery("SELECT * FROM {{table}} WHERE id='".$userrow["guild"]."' LIMIT 1", "guilds"));
    
    
    if (isset($_POST["out"])) {
        
        $member = dorow(doquery("SELECT * FROM {{table}} WHERE id='$charid' LIMIT 1", "users"));
        
        // Errors.
        if ($userrow["guildrank"] < 4) { err("You do not have permission to distribute Guild funds. Please <a href=\"index.php\">go back</a> and try again."); }
        if (!is_numeric($charid)) { err("Invalid input. Please <a href=\"index.php\">go back</a> and try again."); }
        if (!is_numeric($gold)) { err("Invalid input. Please <a href=\"index.php\">go back</a> and try again."); }
        if ($gold < 0) { err("You can't send a negative amount of gold. Please <a href=\"index.php\">go back</a> and try again."); }
        if ($gold > $guild["bank"]) { err("Your Guild does not have that much gold in the bank. Please <a href=\"index.php\">go back</a> and try again."); }
        if ($member == false) { err("Invalid input. Please <a href=\"index.php\">go back</a> and try again."); }
        if ($member["guild"] != $userrow["guild"]) { err("That player is not in your Guild. Please <a href=\"index.php\">go back</a> and try again."); }
        if ($member["id"] == $userrow["id"]) { err("You cannot send Guild money to yourself. Please <a href=\"index.php\">go back</a> and try again."); }
        
        // Do stuff.
        $send = doquery("INSERT INTO {{table}} SET id='', postdate=NOW(), senderid='0', sendername='".$guild["name"]."', recipientid='$charid', recipientname='".$member["charname"]."', status='0', title='Money from your Guild', message='Your Guild has sent you money from the Guild Bank.<br /><br /><b>Do not reply to this message!</b>', gold='$gold'", "messages");
        $update = doquery("UPDATE {{table}} SET bank=bank-$gold WHERE id='".$userrow["guild"]."' LIMIT 1", "guilds");
        display("Post Office", gettemplate("mailbox_sent"));
        
    } elseif (isset($_POST["in"])) {
    
        // Errors.
        if (!is_numeric($_POST["golddeposit"])) { err("Invalid action. Please <a href=\"index.php\">go back</a> and try again."); }
        if ($_POST["golddeposit"] < 1) { err("Deposit amount must be greater than 0."); }
        if ($_POST["golddeposit"] > $userrow["gold"]) { err("You do not have that much money in your pocket."); }
        
        // Do stuff.
        $update = doquery("UPDATE {{table}} SET bank=bank+".$_POST["golddeposit"]." WHERE id='".$userrow["guild"]."' LIMIT 1", "guilds");
        $updatemem = doquery("UPDATE {{table}} SET gold=gold-".$_POST["golddeposit"]." WHERE id='".$userrow["id"]."' LIMIT 1", "users");
        display("Guild Bank", "Thank you for depositing money to the Guild Bank.<br /><br />You may now return to <a href=\"index.php\">Town</a> or to your <a href=\"index.php?do=guildhome\">Guild Hall</a>.");
    
    }
   
}

function guildpromote() {
    
    global $userrow;
    extract($_POST);
    
    $guild = dorow(doquery("SELECT * FROM {{table}} WHERE id='".$userrow["guild"]."' LIMIT 1", "guilds"));
    $member = dorow(doquery("SELECT * FROM {{table}} WHERE id='$charid' LIMIT 1", "users"));
    
    if (isset($_POST["promote"])) {
        
        // Errors.
        if ($userrow["guildrank"] < 4) { err("You do not have permission to promote members. Please <a href=\"index.php\">go back</a> and try again."); }
        if ($userrow["guildrank"] == 4 && $member["guildrank"] >= 3) { err("You do not have permission to promote this member any higher. Please <a href=\"index.php\">go back</a> and try again."); }
        if ($member["guildrank"] == 5) { err("This member cannot be promoted any higher. Please <a href=\"index.php\">go back</a> and try again."); }
        if ($member == false) { err("Invalid input. Please <a href=\"index.php\">go back</a> and try again."); }
        if ($member["guild"] != $userrow["guild"]) { err("That player is not in your Guild. Please <a href=\"index.php\">go back</a> and try again."); }
        
        // Do stuff.
        $update = doquery("UPDATE {{table}} SET guildrank=guildrank+1 WHERE id='$charid' LIMIT 1", "users");
        
    } elseif (isset($_POST["demote"])) {
    
        // Errors.
        if ($userrow["guildrank"] < 4) { err("You do not have permission to demote members. Please <a href=\"index.php\">go back</a> and try again."); }
        if ($userrow["guildrank"] == 4 && $member["guildrank"] > 3) { err("You do not have permission to demote this member. Please <a href=\"index.php\">go back</a> and try again."); }
        if ($userrow["id"] == $member["id"]) { err("You cannot demote yourself. Please <a href=\"index.php\">go back</a> and try again."); }
        if ($member == false) { err("Invalid input. Please <a href=\"index.php\">go back</a> and try again."); }
        if ($member["guild"] != $userrow["guild"]) { err("That player is not in your Guild. Please <a href=\"index.php\">go back</a> and try again."); }
        if ($member["guildrank"] == 1) { guildremove(); }
        
        // Do stuff.
        $update = doquery("UPDATE {{table}} SET guildrank=guildrank-1 WHERE id='$charid' LIMIT 1", "users");
        
    }
    
    display("Guild Ranks", "Thank you for promoting/demoting this user.<br /><br />You may now return to <a href=\"index.php\">Town</a> or to your <a href=\"index.php?do=guildhome\">Guild Hall</a>.");
    
}

function guildapprove() {
    
    global $userrow;
    extract($_POST);
    
    $guild = dorow(doquery("SELECT * FROM {{table}} WHERE id='".$userrow["guild"]."' LIMIT 1", "guilds"));
    $member = dorow(doquery("SELECT * FROM {{table}} WHERE id='$charid' LIMIT 1", "users"));
    $app = dorow(doquery("SELECT * FROM {{table}} WHERE guild='".$userrow["guild"]."' AND charid='$charid' LIMIT 1", "guildapps"));
    
    // Errors.
    if ($userrow["guildrank"] < 4) { err("You do not have permission to approve new members. Please <a href=\"index.php\">go back</a> and try again."); }
    if ($app == false) { err("Invalid input. Please <a href=\"index.php\">go back</a> and try again."); }
    
    // Do stuff.
    if (isset($_POST["approve"])) {
        $updatemem = doquery("UPDATE {{table}} SET guild='".$userrow["guild"]."', guildrank='1', guildtag='".$guild["tagline"]."', tagcolor='".$guild["color1"]."', namecolor='".$guild["color2"]."' WHERE id='".$app["charid"]."' LIMIT 1", "users");
        $updateguild = doquery("UPDATE {{table}} SET members=members+1 WHERE id='".$userrow["guild"]."' LIMIT 1", "guilds");
        $deleteapp = doquery("DELETE FROM {{table}} WHERE guild='".$userrow["guild"]."' AND charid='$charid' LIMIT 1", "guildapps");
        $send = doquery("INSERT INTO {{table}} SET id='', postdate=NOW(), senderid='0', sendername='".$guild["name"]."', recipientid='$charid', recipientname='".$member["charname"]."', status='0', title='Guild Approval', message='The Guild has approved you for membership, and you are now a member of ".$guild["name"].". Congratulations!<br /><br /><b>Do not reply to this message!</b>', gold='0'", "messages");
        display("Approve Members", "Thank you for approving this user.<br /><br />You may now return to <a href=\"index.php\">Town</a> or to your <a href=\"index.php?do=guildhome\">Guild Hall</a>.");
    } else {
        $deleteapp = doquery("DELETE FROM {{table}} WHERE guild='".$userrow["guild"]."' AND charid='$charid' LIMIT 1", "guildapps");
        $send = doquery("INSERT INTO {{table}} SET id='', postdate=NOW(), senderid='0', sendername='".$guild["name"]."', recipientid='$charid', recipientname='".$member["charname"]."', status='0', title='Guild Denial', message='The Guild has denied your application for membership. Sorry.<br /><br /><b>Do not reply to this message!</b>', gold='0'", "messages");
        display("Approve Members", "Thank you for denying this user.<br /><br />You may now return to <a href=\"index.php\">Town</a> or to your <a href=\"index.php?do=guildhome\">Guild Hall</a>.");
    }
    
}

function guildremove() {
    
    global $userrow;
    extract($_POST);
    
    $guild = dorow(doquery("SELECT * FROM {{table}} WHERE id='".$userrow["guild"]."' LIMIT 1", "guilds"));
    $member = dorow(doquery("SELECT * FROM {{table}} WHERE id='$charid' LIMIT 1", "users"));
    
    if (isset($_POST["yes"])) {
        
        $update = doquery("UPDATE {{table}} SET members=members-1 WHERE id='".$guild["id"]."' LIMIT 1", "guilds");
        $updatemem = doquery("UPDATE {{table}} SET guild='0', guildrank='0', guildtag='', tagcolor='', namecolor='' WHERE id='$charid' LIMIT 1", "users");
        $send = doquery("INSERT INTO {{table}} SET id='', postdate=NOW(), senderid='0', sendername='".$guild["name"]."', recipientid='$charid', recipientname='".$member["charname"]."', status='0', title='Guild Removal', message='The Guild has removed you from their membership. Sorry.<br /><br /><b>Do not reply to this message!</b>', gold='0'", "messages");
        display("Remove Members", "Thank you for removing this user.<br /><br />You may now return to <a href=\"index.php\">Town</a> or to your <a href=\"index.php?do=guildhome\">Guild Hall</a>.");
        
    } elseif (isset($_POST["no"])) { 
    
        die(header("Location: index.php?do=guildhome"));
    
    }
    
    
    $pagerow["charid"] = $charid;
    $pagerow["charname"] = $member["charname"];
    display("Remove Member", parsetemplate(gettemplate("guild_remove"), $pagerow));
    
}

function guildnews() {
    
    global $userrow;
    
    $guild = dorow(doquery("SELECT * FROM {{table}} WHERE id='".$userrow["guild"]."' LIMIT 1", "guilds"));
    
    // Errors.
    if ($userrow["guildrank"] < 5) { err("You do not have permission to edit Guild news. Please <a href=\"index.php\">go back</a> and try again."); }
    
    if (isset($_POST["submit"])) {
        
        $query = doquery("UPDATE {{table}} SET news='".$_POST["news"]."' WHERE id='".$userrow["guild"]."' LIMIT 1", "guilds");
        display("Guild News", "Thank you for updating your Guild News.<br /><br />You may now return to <a href=\"index.php\">Town</a> or to your <a href=\"index.php?do=guildhome\">Guild Hall</a>.");
        
    }
    
    if (trim($guild["news"]) == "") { $guild["news"] = "No news yet."; }
    display("Guild News", parsetemplate(gettemplate("guild_news"), $guild));
    
}

function guilddisband() {
    
    global $userrow;
    
    $guild = dorow(doquery("SELECT * FROM {{table}} WHERE id='".$userrow["guild"]."' LIMIT 1", "guilds"));
    
    // Errors.
    if ($userrow["id"] != $guild["founder"]) { err("You do not have permission to disband the Guild. Please <a href=\"index.php\">go back</a> and try again."); }
    
    if (isset($_POST["yes"])) {
        
        $query = doquery("SELECT * FROM {{table}} WHERE guild='".$guild["id"]."'", "users");
        while ($row = mysql_fetch_array($query)) {
            $send = doquery("INSERT INTO {{table}} SET id='', postdate=NOW(), senderid='0', sendername='".$guild["name"]."', recipientid='".$row["id"]."', recipientname='".$row["charname"]."', status='0', title='Guild Disbanded', message='Your Guild leader has chosen to disband the guild. Your member status has been reset, and you can now apply to join another guild if you wish.<br /><br /><b>Do not reply to this message!</b>', gold='0'", "messages");
        }
        $updatemem = doquery("UPDATE {{table}} SET guild='0', guildrank='0', guildtag='', tagcolor='', namecolor='' WHERE guild='".$guild["id"]."'", "users");
        $delete = doquery("DELETE FROM {{table}} WHERE id='".$guild["id"]."'", "guilds");
        $deletebb = doquery("DELETE FROM {{table}} WHERE guild='".$guild["id"]."'", "babblebox");
        display("Disband Guild", "Thank you for disbanding your Guild.<br /><br />You may now return to <a href=\"index.php\">Town</a>.");
        
    } elseif (isset($_POST["no"])) {
    
        die(header("Location: index.php?do=guildhome"));
        
    }
    
    display("Disband Guild", gettemplate("guild_disband"));
    
}

function guildleave() {
    
    global $userrow;
    
    $guild = dorow(doquery("SELECT * FROM {{table}} WHERE id='".$userrow["guild"]."' LIMIT 1", "guilds"));
    
    if (isset($_POST["yes"])) {
        
        $updatemem = doquery("UPDATE {{table}} SET guild='0', guildrank='0', guildtag='', tagcolor='', namecolor='' WHERE id='".$userrow["id"]."'", "users");
        $update = doquery("UPDATE {{table}} SET members=members-1 WHERE id='".$userrow["guild"]."' LIMIT 1", "guilds");
        display("Leave Guild", "Thank you for leaving your Guild.<br /><br />You may now return to <a href=\"index.php\">Town</a>.");
        
    } elseif (isset($_POST["no"])) {
    
        die(header("Location: index.php?do=guildhome"));
        
    }
    
    display("Leave Guild", gettemplate("guild_leave"));
    
}

?>