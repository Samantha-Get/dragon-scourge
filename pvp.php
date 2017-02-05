<?php // pvp.php :: primary duel controller.

include("lib.php");
include("globals.php");
include("fightmods.php");

global $userrow;
$monsterrow = array();

if ($userrow["currentpvp"] == 0) { die(header("Location: index.php")); }
donothing();

function donothing() {
    
    global $userrow, $monsterrow, $fightrow;
    $pvp = dorow(doquery("SELECT * FROM {{table}} WHERE id='".$userrow["currentpvp"]."' LIMIT 1", "pvp"));
    
    // Check if they need to accept challenge.
    if ($pvp["accepted"] == 0 && $pvp["player2id"] == $userrow["id"]) { challenged(); }
    
    // Check if challenge has been declined.
    if ($pvp["accepted"] == 2) { 
        $query = doquery("UPDATE {{table}} SET currentpvp='0',currentaction='In Town' WHERE id='".$userrow["id"]."' LIMIT 1", "users");
        $query = doquery("DELETE FROM {{table}} WHERE id='".$pvp["id"]."' LIMIT 1", "pvp");
        display("Duel Challenge", gettemplate("pvp_declined")); 
    }
    
    // Check if they're dead.
    if ($userrow["currenthp"] <= 0) { youlose(); }
    
    // Check if it's their turn.
    if ($pvp["playerturn"] == $userrow["id"]) { dofight(); }
    
    // Not their turn, so wait.
    dowait();
    
}

function challenged() {
    
    global $userrow, $monsterrow, $fightrow;
    $pvp = dorow(doquery("SELECT * FROM {{table}} WHERE id='".$userrow["currentpvp"]."' LIMIT 1", "pvp"));
    if ($pvp == false) { die("Location: index.php"); }
    $newuserrow = dorow(doquery("SELECT * FROM {{table}} WHERE id='".$pvp["player1id"]."' LIMIT 1", "users"));
    
    if ($newuserrow["charpicture"] != "") {
        $newuserrow["avatar"] = "<img src=\"".$newuserrow["charpicture"]."\" alt=\"".$newuserrow["charname"]."\" width=\"50\" height=\"50\" />";
    } else {
        $newuserrow["avatar"] = "<img src=\"images/users/nopicture.gif\" alt=\"".$newuserrow["charname"]."\" width=\"50\" height=\"50\" />";
    }
    
    if (isset($_POST["yes"])) { 
        
        $query = doquery("UPDATE {{table}} SET accepted='1' WHERE id='".$userrow["currentpvp"]."' LIMIT 1", "pvp");
        $query = doquery("UPDATE {{table}} SET currentaction='Duelling' WHERE id='".$pvp["player1id"]."' OR id='".$pvp["player2id"]."' LIMIT 2", "users");
        dofight();
        
    } elseif (isset($_POST["no"])) {
        
        $query = doquery("UPDATE {{table}} SET accepted='2',playerturn=player1id WHERE id='".$userrow["currentpvp"]."' LIMIT 1", "pvp");
        $query = doquery("UPDATE {{table}} SET currentaction='In Town', currentpvp='0' WHERE id='".$userrow["id"]."' LIMIT 1", "users");
        display("Duel Challenge",parsetemplate(gettemplate("pvp_decline"),$newuserrow));
    
    } else {
    
        display("Duel Challenge",parsetemplate(gettemplate("pvp_challenged"),$newuserrow));
        
    }
    
}

function dowait() {
    
    global $userrow, $monsterrow, $fightrow;
    $pvp = dorow(doquery("SELECT * FROM {{table}} WHERE id='".$userrow["currentpvp"]."' LIMIT 1", "pvp"));
    
    // "monsterrow" now becomes the other player's character.
    if ($pvp["player1id"] == $userrow["id"]) { 
        $monsterrow = dorow(doquery("SELECT * FROM {{table}} WHERE id='".$pvp["player2id"]."' LIMIT 1", "users")); 
    } else {
        $monsterrow = dorow(doquery("SELECT * FROM {{table}} WHERE id='".$pvp["player1id"]."' LIMIT 1", "users")); 
    }
    
    $pagerow = array(
            "message"=>$fightrow["message"],
            "charname"=>$monsterrow["charname"],
            "currenthp"=>$monsterrow["currenthp"],
            "playerphysdamage"=>$fightrow["playerphysdamage"],
            "playermagicdamage"=>$fightrow["playermagicdamage"],
            "playerfiredamage"=>$fightrow["playerfiredamage"],
            "playerlightdamage"=>$fightrow["playerlightdamage"]);
            if ($monsterrow["charpicture"] != "") {
    $pagerow["avatar"] = "<img src=\"".$monsterrow["charpicture"]."\" alt=\"".$monsterrow["charname"]."\" width=\"50\" height=\"50\" />";
    } else {
        $pagerow["avatar"] = "<img src=\"images/users/nopicture.gif\" alt=\"".$monsterrow["charname"]."\" width=\"50\" height=\"50\" />";
    }
    
    display("Duelling",parsetemplate(gettemplate("pvp_wait"),$pagerow));
    
}
    
function dofight() {
    
    global $userrow, $monsterrow, $fightrow, $spells;
    $pvp = dorow(doquery("SELECT * FROM {{table}} WHERE id='".$userrow["currentpvp"]."' LIMIT 1", "pvp"));
    
    // "monsterrow" now becomes the other player's character.
    if ($pvp["player1id"] == $userrow["id"]) { 
        $nextplayer = $pvp["player2id"];
        $monsterrow = dorow(doquery("SELECT * FROM {{table}} WHERE id='".$pvp["player2id"]."' LIMIT 1", "users")); 
    } else {
        $nextplayer = $pvp["player1id"];
        $monsterrow = dorow(doquery("SELECT * FROM {{table}} WHERE id='".$pvp["player1id"]."' LIMIT 1", "users")); 
    }
    
    if (isset($_POST["fight"])) {
        
        playerturn();
        if ($monsterrow["currenthp"] <= 0) { youwin(); }
        updateopponent();
        
        $fightrowimploded = $fightrow["playerphysdamage"].",".$fightrow["playermagicdamage"].",".$fightrow["playerfiredamage"].",".$fightrow["playerlightdamage"].",".$fightrow["message"];
        $query = doquery("UPDATE {{table}} SET fightrow='$fightrowimploded', playerturn='$nextplayer' WHERE id='".$pvp["id"]."' LIMIT 1", "pvp");
        
        $pagerow = array(
            "message"=>$fightrow["message"],
            "charname"=>$monsterrow["charname"],
            "currenthp"=>$monsterrow["currenthp"],
            "playerphysdamage"=>$fightrow["playerphysdamage"],
            "playermagicdamage"=>$fightrow["playermagicdamage"],
            "playerfiredamage"=>$fightrow["playerfiredamage"],
            "playerlightdamage"=>$fightrow["playerlightdamage"]);
        $pagerow["spells"] = dospellslist();
        
        if ($monsterrow["charpicture"] != "") {
            $pagerow["avatar"] = "<img src=\"".$monsterrow["charpicture"]."\" alt=\"".$monsterrow["charname"]."\" width=\"50\" height=\"50\" />";
        } else {
            $pagerow["avatar"] = "<img src=\"images/users/nopicture.gif\" alt=\"".$monsterrow["charname"]."\" width=\"50\" height=\"50\" />";
        }

        display("Duelling",parsetemplate(gettemplate("pvp_wait"),$pagerow));
        
    } elseif (isset($_POST["spell"])) {
    
        if (! is_numeric($_POST["spellid"])) { err("Invalid spell selection."); }
        $isavailable = 0;
        for ($i = 1; $i < 11; $i++) {
            if ($userrow["spell".$i."id"] == $_POST["spellid"]) { $isavailable = 1; }
        }
        if ($isavailable == 0) { err("You don't have that spell."); }
        
        include("spells.php");
        $fightrow["message"] = $spells[$_POST["spellid"]]["fname"]($_POST["spellid"]);
        $monsterrow["currenthp"] -= ($fightrow["playerphysdamage"] + $fightrow["playermagicdamage"] + $fightrow["playerfiredamage"] + $fightrow["playerlightdamage"]);
        if ($monsterrow["currenthp"] <= 0) { youwin(); }
        updateopponent();
        
        $fightrowimploded = $fightrow["playerphysdamage"].",".$fightrow["playermagicdamage"].",".$fightrow["playerfiredamage"].",".$fightrow["playerlightdamage"].",".$fightrow["message"];
        $query = doquery("UPDATE {{table}} SET fightrow='$fightrowimploded', playerturn='$nextplayer' WHERE id='".$pvp["id"]."' LIMIT 1", "pvp");   
        
        $pagerow = array(
            "message"=>$fightrow["message"],
            "charname"=>$monsterrow["charname"],
            "currenthp"=>$monsterrow["currenthp"],
            "playerphysdamage"=>$fightrow["playerphysdamage"],
            "playermagicdamage"=>$fightrow["playermagicdamage"],
            "playerfiredamage"=>$fightrow["playerfiredamage"],
            "playerlightdamage"=>$fightrow["playerlightdamage"]);
        $pagerow["spells"] = dospellslist();
        
        if ($monsterrow["charpicture"] != "") {
            $pagerow["avatar"] = "<img src=\"".$monsterrow["charpicture"]."\" alt=\"".$monsterrow["charname"]."\" width=\"50\" height=\"50\" />";
        } else {
            $pagerow["avatar"] = "<img src=\"images/users/nopicture.gif\" alt=\"".$monsterrow["charname"]."\" width=\"50\" height=\"50\" />";
        }
        
        display("Fighting",parsetemplate(gettemplate("pvp_wait"),$pagerow));
    
    }
    
    if ($pvp["fightrow"] != "") { 
        
        $tempfightrow = explode(",",$pvp["fightrow"]);
        $fightrow["playerphysdamage"] = $tempfightrow[0];
        $fightrow["playermagicdamage"] = $tempfightrow[1];
        $fightrow["playerfiredamage"] = $tempfightrow[2];
        $fightrow["playerlightdamage"] = $tempfightrow[3];
        $fightrow["message"] = $tempfightrow[4];
        
        $pagerow = array(
            "message"=>$fightrow["message"],
            "charname"=>$monsterrow["charname"],
            "currenthp"=>$monsterrow["currenthp"],
            "playerphysdamage"=>$fightrow["playerphysdamage"],
            "playermagicdamage"=>$fightrow["playermagicdamage"],
            "playerfiredamage"=>$fightrow["playerfiredamage"],
            "playerlightdamage"=>$fightrow["playerlightdamage"]);
        $pagerow["spells"] = dospellslist();
        
        if ($monsterrow["charpicture"] != "") {
            $pagerow["avatar"] = "<img src=\"".$monsterrow["charpicture"]."\" alt=\"".$monsterrow["charname"]."\" width=\"50\" height=\"50\" />";
        } else {
            $pagerow["avatar"] = "<img src=\"images/users/nopicture.gif\" alt=\"".$monsterrow["charname"]."\" width=\"50\" height=\"50\" />";
        }
        
        display("Duelling",parsetemplate(gettemplate("pvp_turn"),$pagerow));
        
    } else {
    
        $pagerow = array(
            "charname"=>$monsterrow["charname"],
            "currenthp"=>$monsterrow["currenthp"]);
        $pagerow["spells"] = dospellslist();
        
        if ($monsterrow["charpicture"] != "") {
            $pagerow["avatar"] = "<img src=\"".$monsterrow["charpicture"]."\" alt=\"".$monsterrow["charname"]."\" width=\"50\" height=\"50\" />";
        } else {
            $pagerow["avatar"] = "<img src=\"images/users/nopicture.gif\" alt=\"".$monsterrow["charname"]."\" width=\"50\" height=\"50\" />";
        }
        
        display("Duelling",parsetemplate(gettemplate("pvp_new"),$pagerow));
        
    }
    
}

function playerturn() {
    
    global $userrow, $monsterrow, $fightrow;
    
    // Calculate all damages.
    if ($userrow["physattack"] != 0) {
        $physhit = ceil(rand($userrow["physattack"]*.75, $userrow["physattack"]) / 3);
        $physblock = ceil(rand($monsterrow["physdefense"]*.75, $monsterrow["physdefense"]) / 3);
        $fightrow["playerphysdamage"] = max($physhit - $physblock, 0);
    } else { $fightrow["playerphysdamage"] = 0; }
    
    if ($userrow["magicattack"] != 0) {
        $magichit = ceil(rand($userrow["magicattack"]*.75, $userrow["magicattack"]) / 3);
        $magicblock = ceil(rand($monsterrow["magicdefense"]*.75, $monsterrow["magicdefense"]) / 3);
        $fightrow["playermagicdamage"] = max($magichit - $magicblock, 0);
    } else { $fightrow["playermagicdamage"] = 0; }
    
    if ($userrow["fireattack"] != 0) {
        $firehit = ceil(rand($userrow["fireattack"]*.75, $userrow["fireattack"]) / 3);
        $fireblock = ceil(rand($monsterrow["firedefense"]*.75, $monsterrow["firedefense"]) / 3);
        $fightrow["playerfiredamage"] = max($firehit - $fireblock, 0);
    } else { $fightrow["playerfiredamage"] = 0; }
    
    if ($userrow["lightattack"] != 0) {
        $lighthit = ceil(rand($userrow["lightattack"]*.75, $userrow["lightattack"]) / 3);
        $lightblock = ceil(rand($monsterrow["lightdefense"]*.75, $monsterrow["lightdefense"]) / 3);
        $fightrow["playerlightdamage"] = max($lighthit - $lightblock, 0);
    } else { $fightrow["playerlightdamage"] = 0; }
        
    // Chance to make an excellent hit.
    $toexcellent = rand(0,150);
    if ($toexcellent <= sqrt($userrow["strength"])) { 
        $fightrow["playerphysdamage"] *= 2;
        $fightrow["playermagicdamage"] *= 2;
        $fightrow["playerfiredamage"] *= 2;
        $fightrow["playerlightdamage"] *= 2;
        $fightrow["message"] = "<b>Excellent hit!</b><br />"; 
    }
    
    // Chance for monster to dodge.
    $tododge = rand(0,200);
    if ($tododge <= sqrt($monsterrow["physdefense"])) { 
        $fightrow["playerphysdamage"] = 0;
        $fightrow["playermagicdamage"] = 0;
        $fightrow["playerfiredamage"] = 0;
        $fightrow["playerlightdamage"] = 0;
        $fightrow["message"] = "<b>".$monsterrow["charname"]." dodged the hit!</b><br />"; 
    }
    
    // Now we add Per Turn mods.
    hpleech("player");
    mpleech("player");
    
    // Subtract all damage from monster's hp.
    $monsterrow["currenthp"] -= ($fightrow["playerphysdamage"] + $fightrow["playermagicdamage"] + $fightrow["playerfiredamage"] + $fightrow["playerlightdamage"]);
    
}

function youwin() {
    
    global $userrow, $monsterrow, $fightrow;
    $pvp = dorow(doquery("SELECT * FROM {{table}} WHERE id='".$userrow["currentpvp"]."' LIMIT 1", "pvp"));
    
    // "monsterrow" now becomes the other player's character.
    if ($pvp["player1id"] == $userrow["id"]) { 
        $nextplayer = $pvp["player2id"];
    } else {
        $nextplayer = $pvp["player1id"];
    }
    
    $template = "pvp_win";

    $userrow["currentaction"] = "In Town";
    $userrow["currentfight"] = 0;
    $userrow["currentpvp"] = 0;
    
    // Now we add Per Kill mods.
    hpgain();
    mpgain();
    
    // Update for new stats.
    updateopponent();
    updateuserrow();
    $fightrowimploded = $fightrow["playerphysdamage"].",".$fightrow["playermagicdamage"].",".$fightrow["playerfiredamage"].",".$fightrow["playerlightdamage"].",".$fightrow["message"];
    $query = doquery("UPDATE {{table}} SET fightrow='$fightrowimploded', playerturn='$nextplayer' WHERE id='".$pvp["id"]."' LIMIT 1", "pvp");
    
    // And we're done.
    $pagerow = array(
        "message"=>$fightrow["message"],
        "monstername"=>$monsterrow["charname"],
        "monsterhp"=>$userrow["currentmonsterhp"],
        "playerphysdamage"=>$fightrow["playerphysdamage"],
        "playermagicdamage"=>$fightrow["playermagicdamage"],
        "playerfiredamage"=>$fightrow["playerfiredamage"],
        "playerlightdamage"=>$fightrow["playerlightdamage"]);
        
    if ($monsterrow["charpicture"] != "") {
        $pagerow["avatar"] = "<img src=\"".$monsterrow["charpicture"]."\" alt=\"".$monsterrow["charname"]."\" width=\"50\" height=\"50\" />";
    } else {
        $pagerow["avatar"] = "<img src=\"images/users/nopicture.gif\" alt=\"".$monsterrow["charname"]."\" width=\"50\" height=\"50\" />";
    }
        
    display("Victory!",parsetemplate(gettemplate($template),$pagerow));
    
}

function youlose() {
    
    global $userrow, $monsterrow, $fightrow;
    $pvp = dorow(doquery("SELECT * FROM {{table}} WHERE id='".$userrow["currentpvp"]."' LIMIT 1", "pvp"));
    
    if ($pvp["player1id"] == $userrow["id"]) { 
        $monsterrow = dorow(doquery("SELECT * FROM {{table}} WHERE id='".$pvp["player2id"]."' LIMIT 1", "users")); 
    } else {
        $monsterrow = dorow(doquery("SELECT * FROM {{table}} WHERE id='".$pvp["player1id"]."' LIMIT 1", "users")); 
    }
    
    $tempfightrow = explode(",",$pvp["fightrow"]);
    $fightrow["playerphysdamage"] = $tempfightrow[0];
    $fightrow["playermagicdamage"] = $tempfightrow[1];
    $fightrow["playerfiredamage"] = $tempfightrow[2];
    $fightrow["playerlightdamage"] = $tempfightrow[3];
    $fightrow["message"] = $tempfightrow[4];
    
    // Then put them in town & reset fight stuff.
    $userrow["currentaction"] = "In Town";
    $userrow["currentfight"] = 0;
    $userrow["currentpvp"] = 0;
    $userrow["currenthp"] = ceil($userrow["maxhp"] / 4);
    
    // Update.
    updateuserrow();
    $query = doquery("DELETE FROM {{table}} WHERE id='".$pvp["id"]."' LIMIT 1", "pvp");
    
    // And we're done.
    $pagerow = array(
        "message"=>$fightrow["message"],
        "monstername"=>$monsterrow["charname"],
        "monsterhp"=>$userrow["currentmonsterhp"],
        "playerphysdamage"=>$fightrow["playerphysdamage"],
        "playermagicdamage"=>$fightrow["playermagicdamage"],
        "playerfiredamage"=>$fightrow["playerfiredamage"],
        "playerlightdamage"=>$fightrow["playerlightdamage"]);
        
    if ($monsterrow["charpicture"] != "") {
        $pagerow["avatar"] = "<img src=\"".$monsterrow["charpicture"]."\" alt=\"".$monsterrow["charname"]."\" width=\"50\" height=\"50\" />";
    } else {
        $pagerow["avatar"] = "<img src=\"images/users/nopicture.gif\" alt=\"".$monsterrow["charname"]."\" width=\"50\" height=\"50\" />";
    }
    
    display("Thou Art Dead.",parsetemplate(gettemplate("pvp_lose"),$pagerow));

}

function updateopponent() {
    
    global $monsterrow;
    
    $querystring = "";
    foreach($monsterrow as $a=>$b) {
        $querystring .= "$a='$b',";
    }
    $querystring = rtrim($querystring, ",");
    
    $query = doquery("UPDATE {{table}} SET $querystring WHERE id='".$monsterrow["id"]."' LIMIT 1", "users");
    
}

function dospellslist() {
    
    global $userrow, $spells;
    $options = "";
    for ($i = 1; $i < 11; $i++) {
        if ($userrow["spell".$i."id"] != 0) { 
            $options .= "<option value=\"".$userrow["spell".$i."id"]."\">".$userrow["spell".$i."name"]."</option>\n";
        }
    }
    if ($options != "") { 
        $list = "<select name=\"spellid\">$options</select> <input type=\"submit\" name=\"spell\" value=\"Cast Spell\" />";
    } else { $list = "<input type=\"submit\" name=\"spell\" value=\"Cast Spell\" disabled=\"disabled\" />"; }
    return $list;
    
}

?>