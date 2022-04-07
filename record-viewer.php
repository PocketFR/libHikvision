<?php
if (!isset($site_name) || !isset($camera_name) || !isset($cfgCCTVPaths)) {
	header('Location: .');
	exit;
}
// This should be the path to your data directory, ending in a /.
$tmpRelativePath ='./temp/';
$downloadRelativePath ='./download/'; // must be a link to tmp folder with header Content-disposition: attachment
$tmpPath = '/var/www/html/records/temp/'; # Path where temporary mp4 files can go

$thumbnail_real = $tmpPath.$site_name.'_'.$camera_name;
$thumbnail_relative = $tmpRelativePath.$site_name.'_'.$camera_name;
require_once 'libHikvision.php';
/**
* Name: Preserve and update/rebuild query string<br>
* @param Example:
* Example URL: http://www.site.com/?category=foo&order=desc&page=2
*
* <a href="<?php echo queryString('order','asc'); ?>">Order ASC</a>
*
* Output HTML: <a href="?category=foo&amp;order=asc&amp;page=2">Order ASC</a>
* Output URL: http://www.site.com/?category=foo&order=asc&page=2
*
* Not http://www.site.com/?category=foo&order=desc&page=2&order=asc
*/
function queryString($str,$val)
{
	$queryString = array();
	$queryString = $_GET;
	$queryString[$str] = $val;
	$queryString = "?".htmlspecialchars(http_build_query($queryString),ENT_QUOTES);
	
	return $queryString;
}

$cctv = new hikvisionCCTV( $cfgCCTVPaths );
//
//Check Logs
if (isset($_GET["logs"])) {
	header('Content-type: text/plain');
	header('Content-Disposition: filename="'.$site_name.'-'.$camera_name.'.log"');
	echo $cctv->exportlogs();
	exit;
}

//
//Check SeeAll
if(
	isset($_GET['seeAll']) &&
	isset($_GET['start']) &&
	isset($_GET['end']) &&
	preg_match("/^\d{4}\-(0[1-9]|1[012])\-(0[1-9]|[12][0-9]|3[01])$/",$_GET['start']) === 1 &&
	checkdate(explode("-",$_GET['start'])[1],explode("-",$_GET['start'])[2],explode("-",$_GET['start'])[0]) &&
	preg_match("/^\d{4}\-(0[1-9]|1[012])\-(0[1-9]|[12][0-9]|3[01])$/",$_GET['end']) === 1 &&
	checkdate(explode("-",$_GET['end'])[1],explode("-",$_GET['end'])[2],explode("-",$_GET['end'])[0]) 
	)
{
	$dayBegin = strtotime($_GET['start']);
	$dayEnd = strtotime($_GET['end']) + 86399;
	$filename = $cctv->extractSegmentsBetweenDatesMP4($site_name.'_'.$camera_name,$dayBegin,$dayEnd,$tmpPath);
	if (isset($_GET["download"])) {
		header('Location: '.$downloadRelativePath.$filename);
	} else {
		header('Location: '.$tmpRelativePath.$filename);
	}
	exit();
}
//
//Check SeeSelecteds
if(
	isset($_GET['seeSelected']) &&
	isset($_GET['selecteds']) 
	)
{
	$selecteds = json_decode($_GET["selecteds"], true);

	$filename = $cctv->extractSegmentsMP4($site_name.'_'.$camera_name,$selecteds,$tmpPath);
	if (isset($_GET["download"])) {
		header('Location: '.$downloadRelativePath.$filename);
	} else {
		header('Location: '.$tmpRelativePath.$filename);
	}
	exit();
}


//
//Check thumbnail
if(
	isset($_GET['ajax-thumbnail']) &&
	isset($_GET['file']) &&
	isset($_GET['dir']) &&
	isset($_GET['offset']) &&
	is_numeric($_GET['file']) &&
	is_numeric($_GET['dir']) &&
	is_numeric($_GET['offset']) )
{
	$cctv->extractThumbnail(
			$_GET['dir'],
			$_GET['file'],
			$_GET['offset'],
			$thumbnail_real.'_'.$_GET['dir'].'_'.$_GET['file'].'_'.$_GET['offset'].'.jpg'
			// $thumbnail_real.'_'.$thumbnail_time.'.jpg'
			);
	exit;
}


//
// Check query string to see if we need to download a file.
if(
	isset($_GET['datadir']) &&
	isset($_GET['file']) &&
	isset($_GET['start']) &&
	isset($_GET['end']) &&
	is_numeric($_GET['datadir']) &&
	is_numeric($_GET['file']) &&
	is_numeric($_GET['start']) &&
	is_numeric($_GET['end']) )
{
	$filename = '';
	if (isset($_GET["full"])) {
		$filename = $site_name.'_'.$camera_name.'.'.$_GET['datadir'].'.'.$_GET['file'].'.Full.mp4';
		$cctv->extractFullMP4(
			$site_name.'_'.$camera_name,$_GET['datadir'],$_GET['file'],$tmpPath
		);		
	} else {
		$filename = $site_name.'_'.$camera_name.'.'.$_GET['datadir'].'.'.$_GET['file'].'.'.$_GET['start'].'.'.$_GET['end'].'.mp4';
		$cctv->extractSegmentMP4(
			$site_name.'_'.$camera_name,$_GET['datadir'],$_GET['file'],$_GET['start'],$_GET['end'],$tmpPath
		);
	}
	if (isset($_GET["download"])) {
		header('Location: '.$downloadRelativePath.$filename);
	} else {
		header('Location: '.$tmpRelativePath.$filename);
	}
	exit();
}

$dayBegin = strtotime("today");
$dayEnd = strtotime("tomorrow") - 1;
// Need to check is valid date!
if( isset($_GET['SearchBegin']) && isset($_GET['SearchEnd'])) 
{
	$dayBegin = strtotime($_GET['SearchBegin']);
	$dayEnd = strtotime($_GET['SearchEnd']) + 86399;
}
//
// Determine period to view recordings for.
$filterDay = $dayBegin;
if( isset($_GET['Day']) && is_numeric($_GET['Day']) )
{
	$filterDay = $_GET['Day'];
}
//
// Build array containing data.
$segmentsByDay = array();
// $segments = $cctv->getSegmentsBetweenDates($dayBegin, $dayEnd );
$segments = $cctv->getSegmentsBetweenDates($dayBegin < strtotime('-30 days midnight GMT') ? strtotime('-30 days midnight GMT') : $dayBegin, $dayEnd );
foreach($segments as $segment)
{
	$startTime = $segment['cust_startTime'];
	$index = strtotime("midnight", $startTime);
	
	if(!isset( $segmentsByDay[$index] ))
	{
		$segmentsByDay[$index] = array(
			'start' => $index,
			'end' => strtotime("tomorrow", $startTime) - 1,
			'segments' => array()
			);
	}
	$segmentsByDay[$index]['segments'][] = $segment;
}
ksort($segmentsByDay);
// =============================================================================
?>

<!doctype html>
<html>
<head>
<title><?php echo 'Enregistements '.$site_name.' - '.$camera_name; ?></title>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
</head>
<body>
<style type="text/css">
body{font-family:'Bitstream Vera Sans','DejaVu Sans',Tahoma,sans-serif;font-size:13px;background-color:#e8e8e8}
button{font-family:'Bitstream Vera Sans','DejaVu Sans',Tahoma,sans-serif;font-size:13px}
.visualLabel{float:right;background-color:#e8e8e8;border-radius:10px;padding:0.3em;font-size:x-small}
form{margin-bottom:1em}
fieldset{margin:0;padding:0;padding-top:1em;border:0}
input[type="text"],input[type="password"],textarea,select{border:1px solid #E8E8E8;padding:5px;display:inline-block;width:320px;box-sizing:border-box}
select{width:auto}
a.button,input[type="submit"]{border:1px solid #D8D8D8;background:#F1F1F1;box-shadow:inset 0 1px 3px #fff,inset 0 -15px #E8E8E8,0 0 3px #E8E8E8;color:#000;text-shadow:0 1px #E8E8E8;padding:5px 30px;cursor:pointer}
a.button:hover,input[type="submit"]:hover{border:1px solid #DAAF00;background-color:#FFCC00;box-shadow:inset 0 1px 3px #fff,inset 0 -15px #DAAF00,0 0 3px #FFCC00;text-shadow:0 1px #DAAF00}
label{display:inline-block;width:120px;padding:5px;text-align:right}
table{width:100%;border-collapse:collapse;text-align:left;border-color:#E8E8E8;border:1px solid #e8e8e8;margin-bottom:1em}
thead th{color:#494949;font-size:1.0em;padding:8px;background-color:#F1F1F1;border-top:5px solid #E8E8E8}
tbody tr td,tfoot tr td,tfoot tr th{padding:9px 8px 8px;font-size:0.9em;background:#fff;white-space:nowrap}
td{border:1px solid #E8E8E8}
a.button{line-height:30px;padding:5px 10px}
.formField{margin:0 0 5px;display:block}

.cctvLive img{max-width:100%;height:auto}
.cctvImg{ position:relative;float:left;clear:none;overflow:hidden;width:320px;height:180px;margin:2px;max-width:100%}
.cctvImg img{position:relative;z-index:1}
.cctvImg p{display:block;position:absolute;width:100%;bottom:0;left:0;z-index:2;text-align:center;background-color:#494949;opacity:0.8;color:#fff;margin-bottom:3px;font-size:14px;padding:4px}
.cctvDay:after{display:block;content:' ';clear:both}
.cctvDay{display:none}

#LeftPanel{width:210px;float:left;margin-right: 15px}
#RightPanel{position:relative;margin-left:210px;padding-left:20px}

.ruler, .ruler li {
    margin: 0;
    padding: 0;
    list-style: none;
    display: inline-block;
}
/* IE6-7 Fix */
.ruler, .ruler li {
    *display: inline;
}
.ruler {
    background: lightYellow;
    box-shadow: 0 -1px 1em hsl(60, 60%, 84%) inset;
    border-radius: 2px;
    /*border: 1px solid #ccc;
    color: #ccc;*/
    margin: 0;
    height: 3em;
    white-space: nowrap;
	width: 100%;
}
.ruler li {
    padding-left: 16.666666666666666666666666666667%;
    width: 2em;
    margin: 3.1em -1em -3.1em;
    text-align: center;
    position: relative;
    text-shadow: 1px 1px hsl(60, 60%, 84%);
	z-index: 1;
}
.ruler li:before {
    content: '';
    position: absolute;
    border-left: 1px solid #ccc;
    height: 2em;
    top: -2em;
    right: 1em;
}

.ruler-record {
	background-color: red;
	position: relative;
	height: 3em;
	display: inline-block;
	top: -4.3em;
	border: 1px;
	cursor: pointer;
}

.ruler-record .tooltiptext {
  visibility: hidden;
  width: 120px;
  background-color: black;
  color: #fff;
  text-align: center;
  border-radius: 6px;
  padding: 5px 0;
  
  /* Position the tooltip */
  position: absolute;
  z-index: 2;
  top: 100%;
  left: 50%;
  margin-left: -120px;
}

.ruler-record:hover .tooltiptext {
  visibility: visible;
}

details {
    border: 1px solid #aaa;
    border-radius: 4px;
    padding: .5em .5em 0;
}

summary {
    font-weight: bold;
    margin: -.5em -.5em 0;
    padding: .5em;
	cursor: pointer;
}

details[open] {
    padding: .5em;
}

details[open] summary {
    border-bottom: 1px solid #aaa;
    margin-bottom: .5em;
}

button {
	cursor: pointer;
}
.download {
	right: 10px;
	position: absolute;
	z-index: 10;
	color: white;
	font-size: 2em;
	text-shadow: 1px 0 0 #000, -1px 0 0 #000, 0 1px 0 #000, 0 -1px 0 #000, 1px 1px #000, -1px -1px 0 #000, 1px -1px 0 #000, -1px 1px 0 #000;
}
.checkbox {
	position: absolute;
	z-index: 10;
	float: left;
}
</style>
<script>
var site_name = "<?php echo $site_name; ?>";
var camera_name = "<?php echo $camera_name; ?>";
// playVideoWindow(0,88,176652800,221621788,'/cameras/Thumbnails/CAM02_0_88_176652800.jpg')
function playVideoWindow( _cust_dataDirNum, _cust_fileNum, _startOffset, _endOffset ,title )
{
	var newWindow = window.open("", "_blank", "toolbar=no,scrollbar=no,resizable=yes,width=1280,height=800");
	newWindow.document.write("<!DOCTYPE html><html><head><title>"+title+"</title></head><body style=\"margin:0\"><video controls muted autoplay width=\"100%\">" +
		"<source src=\"?datadir="+ _cust_dataDirNum +"&amp;file="+_cust_fileNum+"&amp;start="+_startOffset+"&amp;end="+_endOffset+"\" type=\"video/mp4\"></video></body></html>");
}

function highlight(cctvid) {
	var list= document.getElementsByClassName("cctvImg");
	for (var i = 0; i < list.length; i++) {
		list[i].children[3].style = '';
	}
	document.getElementById("cctv-"+cctvid).children[3].style.backgroundColor = "#aa8820";
}

window.onbeforeunload = confirmExit;
function confirmExit() {
	document.getElementById("loading").style.display = "";
}
function errorimg(e) {
	e.src='images/loading.gif';
	var xhr = new XMLHttpRequest();
	xhr.onreadystatechange = (function(x, e) {
	   return function() {
			if (x.readyState == XMLHttpRequest.DONE) {
				e.src='<?php echo $tmpRelativePath; ?>'+site_name+'_'+camera_name+'_'+e.dataset.dir+'_'+e.dataset.file+'_'+e.dataset.offset+'.jpg';
			}
	   }
	})(xhr, e);
	xhr.open('GET', '?ajax-thumbnail&dir='+e.dataset.dir+'&file='+e.dataset.file+'&offset='+e.dataset.offset);
	xhr.send();
}
function select_all() {
	document.querySelectorAll(".checkbox").forEach(function(e) {
		e.checked = true;
	});
	select();
}
function select_none() {
	document.querySelectorAll(".checkbox").forEach(function(e) {
		e.checked = false;
	});
	select();
}
function select() {
	if (document.querySelectorAll(".checkbox:checked").length == 0 ) {
		document.getElementById("voir").innerHTML = 'Voir toutes les vidéos';
	} else {
		document.getElementById("voir").innerHTML = 'Voir la sélection';
	}
}
function see_selected(e) {
	if (document.querySelectorAll(".checkbox:checked").length == 0 ) {
		if (e.altKey) {
			window.open('?seeAll&download&start=' + document.getElementById('SearchBegin').value + '&end=' + document.getElementById('SearchEnd').value);
		} else {
			window.open('?seeAll&start=' + document.getElementById('SearchBegin').value + '&end=' + document.getElementById('SearchEnd').value);
		}
	} else {
		let selected = [];
		document.querySelectorAll(".checkbox:checked").forEach( function(e) {
			selected.push({cust_startTime: e.attributes["data-starttime"].value, cust_endTime: e.attributes["data-endtime"].value, cust_dataDirNum: e.attributes["data-dir"].value, cust_fileNum: e.attributes["data-file"].value, startOffset: e.attributes["data-start"].value, endOffset: e.attributes["data-end"].value});
		});
		selected.sort(function(a,b) {
			return a.time - b.time;
		});
		if (e.altKey) {
			window.open('?seeSelected&download&selecteds=' + encodeURIComponent(JSON.stringify(selected)));
		} else {
			window.open('?seeSelected&selecteds=' + encodeURIComponent(JSON.stringify(selected)));
		}
	}
}
</script>
<div id="loading" style="position:fixed; top:0; left:0; width: 100%; height:100%; background-color: #00000085; z-index:99; display:none;">
	<div style="position:absolute;top: calc(50% - 112px);left: calc(50% - 150px);background-color: white;width: 320px;z-index:100;text-align:center; border-radius: 30px;">
		<p style="font-weight: bold;">Chargement en cours ...</p>
		<img src="images/loading.gif"/>
	</div>
</div>
<h1><?php echo 'Enregistements '.$site_name.' - '.$camera_name; ?></h1>
<button onclick="select_all();" style="position: absolute; top: 20px; right: 571px;">Selectionner tout</button>
<button onclick="select_none();" style="position: absolute; top: 20px; right: 431px;">Déselectionner tout</button>
<button id="voir" onclick="see_selected(event);" style="position: absolute; top: 20px; right: 276px; width: 156px;">Voir toutes les vidéos</button>
<button onclick="window.location='.'" style="position: absolute; top: 20px; right: 145px;">Liste des caméras</button>
<button onclick="window.location='../live/'" style="position: absolute; top: 20px; right: 20px;">Regarder le direct</button>
<button onclick="window.open('?logs')" style="position: absolute; top: 50px; right: 20px;">Voir les Logs</button>
 <div id="LeftPanel">
	<form method="get" onsubmit="window.location.hash=''; return true;">
		<fieldset>
			<div class="formField">
			<label for="SearchBegin" style="width:30px;display:inline-block">Du</label>
			<input type="date" id="SearchBegin" name="SearchBegin" value="<?php echo date('Y-m-d', $dayBegin); ?>" />
			</div>
			<div class="formField">
			<label for="SearchEnd" style="width:30px;display:inline-block">Au</label>
			<input type="date" id="SearchEnd" name="SearchEnd" value="<?php echo date('Y-m-d', $dayEnd); ?>" />
			</div>
			<label for="frmSubmit" style="width:30px">&nbsp;</label>
			<input type="submit" value="Rechercher" id="frmSubmit">
		</fieldset>
	</form>
	<table>
		<thead>
			<tr><th>Date</th></tr>
		</thead>
		<tbody>
		<?php
		foreach($segmentsByDay as $day)
		{
			echo '<tr><td><a href="'.
					queryString('Day',$day['start']).
					'">'.
					strftime('%A %d %B %Y', $day['start']).'</a>'.
					'<span class="visualLabel">'.count($day['segments']).'</span></td></tr>';
		}
		?>
		</tbody>
	</table>	
&nbsp;
</div>

<div id="RightPanel">
<?php
echo '<h2 style="margin-bottom: 0;">'.strftime('%A %d %B %Y', $filterDay).'</h2>';
if(isset($segmentsByDay[$filterDay]))
{	
	$recordings = $segmentsByDay[$filterDay]['segments'];
	// Sort recordings in order of most recent.	
	// usort($recordings, function ($a, $b) {
		// return strcmp( $b['cust_startTime'], $a['cust_startTime'] );
		// });
	$cctvImg = '';
	$timerule1 = '<div style="height: 5em;"><ul class="ruler"><li>1</li><li>2</li><li>3</li><li>4</li><li>5</li></ul>';
	$timerule2 = '<div style="height: 5em;"><ul class="ruler"><li>7</li><li>8</li><li>9</li><li>10</li><li>11</li></ul>';
	$timerule3 = '<div style="height: 5em;"><ul class="ruler"><li>13</li><li>14</li><li>15</li><li>16</li><li>17</li></ul>';
	$timerule4 = '<div style="height: 5em;"><ul class="ruler"><li>19</li><li>20</li><li>21</li><li>22</li><li>23</li></ul>';
	$cctvId = 0;
	$cumulduree1 = 0;
	$cumulduree2 = 0;
	$cumulduree3 = 0;
	$cumulduree4 = 0;
	$tableau = array();
	foreach($recordings as $recording)
	{
		$cctvId ++;
		$startTime = strftime('le %d/%m de %H:%M:%S',$recording['cust_startTime']);
		$rulestartTime = strftime('%H:%M:%S',$recording['cust_startTime']);
		$endTime = strftime('%H:%M:%S', $recording['cust_endTime']);
		$duree = $recording['cust_endTime'] - $recording['cust_startTime'];
		$ruleduree = $duree * 100 / 21600;
		
		if ($recording['cust_startTime'] - $filterDay < 21600) {
			$rulestart = ($recording['cust_startTime'] - $filterDay - $cumulduree1) * 100 / 21600;
			if ($recording['cust_endTime'] - $filterDay > 21600) {
				$duree1 = ($filterDay + 21600) - $recording['cust_startTime'];
				$duree2 = $recording['cust_endTime'] - ($filterDay + 21600);
				$ruleduree1 = $duree1 * 100 / 21600;
				$ruleduree2 = $duree2 * 100 / 21600;
				$timerule1 .= '<div class="ruler-record" style="left: '.$rulestart.'%; width: '.$ruleduree1.'%;" onclick="highlight('.$cctvId.'); window.location=\'#cctv-'.$cctvId.'\'"><span class="tooltiptext">'.$rulestartTime.' à '. $endTime .' ('.$duree.'s)</span></div>';
				$timerule2 .= '<div class="ruler-record" style="left: 0%; width: '.$ruleduree2.'%;" onclick="highlight('.$cctvId.'); window.location=\'#cctv-'.$cctvId.'\'"><span class="tooltiptext">'.$rulestartTime.' à '. $endTime .' ('.$duree.'s)</span></div>';
				$cumulduree1 += $duree1;
				$cumulduree2 += $duree2;
			} else {
				$timerule1 .= '<div class="ruler-record" style="left: '.$rulestart.'%; width: '.$ruleduree.'%;" onclick="highlight('.$cctvId.'); window.location=\'#cctv-'.$cctvId.'\'"><span class="tooltiptext">'.$rulestartTime.' à '. $endTime .' ('.$duree.'s)</span></div>';
				$cumulduree1 += $duree;
			}
		} elseif ($recording['cust_startTime'] - $filterDay < 43200) {
			$rulestart = ($recording['cust_startTime'] - $filterDay - $cumulduree2 - 21600) * 100 / 21600;
			if ($recording['cust_endTime'] - $filterDay > 43200) {
				$duree1 = ($filterDay + 43200) - $recording['cust_startTime'];
				$duree2 = $recording['cust_endTime'] - ($filterDay + 43200);
				$ruleduree1 = $duree1 * 100 / 21600;
				$ruleduree2 = $duree2 * 100 / 21600;
				$timerule2 .= '<div class="ruler-record" style="left: '.$rulestart.'%; width: '.$ruleduree1.'%;" onclick="highlight('.$cctvId.'); window.location=\'#cctv-'.$cctvId.'\'"><span class="tooltiptext">'.$rulestartTime.' à '. $endTime .' ('.$duree.'s)</span></div>';
				$timerule3 .= '<div class="ruler-record" style="left: 0%; width: '.$ruleduree2.'%;" onclick="highlight('.$cctvId.'); window.location=\'#cctv-'.$cctvId.'\'"><span class="tooltiptext">'.$rulestartTime.' à '. $endTime .' ('.$duree.'s)</span></div>';
				$cumulduree2 += $duree1;
				$cumulduree3 += $duree2;
			} else {
				$timerule2 .= '<div class="ruler-record" style="left: '.$rulestart.'%; width: '.$ruleduree.'%;" onclick="highlight('.$cctvId.'); window.location=\'#cctv-'.$cctvId.'\'"><span class="tooltiptext">'.$rulestartTime.' à '. $endTime .' ('.$duree.'s)</span></div>';
				$cumulduree2 += $duree;
			}
		} elseif ($recording['cust_startTime'] - $filterDay < 64800) {
			$rulestart = ($recording['cust_startTime'] - $filterDay - $cumulduree3 - 43200) * 100 / 21600;
			if ($recording['cust_endTime'] - $filterDay > 64800) {
				$duree1 = ($filterDay + 64800) - $recording['cust_startTime'];
				$duree2 = $recording['cust_endTime'] - ($filterDay + 64800);
				$ruleduree1 = $duree1 * 100 / 21600;
				$ruleduree2 = $duree2 * 100 / 21600;
				$timerule3 .= '<div class="ruler-record" style="left: '.$rulestart.'%; width: '.$ruleduree1.'%;" onclick="highlight('.$cctvId.'); window.location=\'#cctv-'.$cctvId.'\'"><span class="tooltiptext">'.$rulestartTime.' à '. $endTime .' ('.$duree.'s)</span></div>';
				$timerule4 .= '<div class="ruler-record" style="left: 0%; width: '.$ruleduree2.'%;" onclick="highlight('.$cctvId.'); window.location=\'#cctv-'.$cctvId.'\'"><span class="tooltiptext">'.$rulestartTime.' à '. $endTime .' ('.$duree.'s)</span></div>';
				$cumulduree3 += $duree1;
				$cumulduree4 += $duree2;
			} else {
				$timerule3 .= '<div class="ruler-record" style="left: '.$rulestart.'%; width: '.$ruleduree.'%;" onclick="highlight('.$cctvId.'); window.location=\'#cctv-'.$cctvId.'\'"><span class="tooltiptext">'.$rulestartTime.' à '. $endTime .' ('.$duree.'s)</span></div>';
				$cumulduree3 += $duree;
			}
		} else {
			$rulestart = ($recording['cust_startTime'] - $filterDay - $cumulduree4 - 64800) * 100 / 21600;
			$timerule4 .= '<div class="ruler-record" style="left: '.$rulestart.'%; width: '.$ruleduree.'%;" onclick="highlight('.$cctvId.'); window.location=\'#cctv-'.$cctvId.'\'"><span class="tooltiptext">'.$rulestartTime.' à '. $endTime .' ('.$duree.'s)</span></div>';
			$cumulduree4 += $duree;
		}
		$lienvideo = '<a href="?datadir='.$recording['cust_dataDirNum'].'&file='.$recording['cust_fileNum'].'&start='.$recording['startOffset'].'&end='.$recording['endOffset'].'" target="_blank">';
		$checkbox = '<input type="checkbox" class="checkbox" onclick="if (document.querySelectorAll(\'.checkbox:checked\').length == 0 ) {document.getElementById(\'voir\').innerHTML = \'Voir toutes les vidéos\';} else {document.getElementById(\'voir\').innerHTML = \'Voir la sélection\';}" data-dir="'.$recording['cust_dataDirNum'].'" data-file="'.$recording['cust_fileNum'].'" data-start="'.$recording['startOffset'].'" data-end="'.$recording['endOffset'].'" data-starttime="'.$recording['cust_startTime'].'" data-endtime="'.$recording['cust_endTime'].'" />';
		$lienvideodownload = '<a class="download" href="?datadir='.$recording['cust_dataDirNum'].'&file='.$recording['cust_fileNum'].'&start='.$recording['startOffset'].'&end='.$recording['endOffset'].'&download" target="_blank">&DownArrowBar;</a>';
		$thumbnail = $thumbnail_relative.'_'.$recording['cust_dataDirNum'].'_'.$recording['cust_fileNum'].'_'.$recording['startOffset'].'.jpg';
		
		$video = new stdClass();
		$video->start = $recording['cust_startTime'];
		$video->stop = $recording['cust_endTime'];
		$video->duree = $recording['cust_endTime'] - $recording['cust_startTime'];
		$video->mozaique = '<div class="cctvImg" id="cctv-'.$cctvId.'" onclick="highlight('.$cctvId.');">'.$checkbox.$lienvideodownload.$lienvideo.
				'<img src="'.$thumbnail.'" loading="lazy" width="320" height="180" onerror="errorimg(this);" data-dir="'.$recording['cust_dataDirNum'].'" data-file="'.$recording['cust_fileNum'].'" data-offset="'.$recording['startOffset'].'"/></a>'.
				'<p>'.$startTime.' à '. $endTime .' ('.$duree.'s)</p>'.
				'</div>';
		$tableau[] = $video;
	}
	$dureecumulee = $cumulduree1+$cumulduree2+$cumulduree3+$cumulduree4;
	echo '<h4 style="margin-top: 0; margin-left: 2em;">'.count($recordings).' enregistrements pour une durée totale de '.strftime('%kh %Mmin. %Ssec.',$dureecumulee).'</h4>';
	$timerule1 .= '</div>';
	$timerule2 .= '</div>';
	$timerule3 .= '</div>';
	$timerule4 .= '</div>';
	echo '<details><summary>Chronologie</summary>';
	echo $timerule1;
	echo $timerule2;
	echo $timerule3;
	echo $timerule4;
	echo '</details>';
?>
<h3>Vidéos</h3>
<div style="float: right; margin-top: -3em;">Trier par: <select onchange="video_display(JSON.parse(this.value));"><option value='["date","asc"]'>Date &darr;</option><option value='["date","desc"]' selected>Date &uarr;</option><option value='["duree","asc"]'>Durée &darr;</option><option value='["duree","desc"]'>Durée &uarr;</option></select></div>
<div id=videos></div>
<script>
var videos = <?php echo json_encode($tableau) ?>;
function video_display(tri,ordre) {
  if(Array.isArray(tri)) {
	  ordre = tri[1];
	  tri = tri[0];
  }
  let videosdiv = document.getElementById("videos");
  videosdiv.innerHTML = "";
  if (tri == 'date') {
	if (ordre == 'asc') {
		videos.sort(function(a,b) {
			return a.start - b.start;
		});
	} else {
		videos.sort(function(a,b) {
			return b.start - a.start;
		});
	}
  } else {
	if (ordre == 'asc') {
		videos.sort(function(a,b) {
			return a.duree - b.duree;
		});
	} else {
		videos.sort(function(a,b) {
			return b.duree - a.duree;
		});
	}
  }
  videos.forEach(function (video) {
		videosdiv.insertAdjacentHTML( 'beforeend', video.mozaique );
  });
  [].forEach.call(document.getElementsByClassName("download"), function (el) {
	  el.onclick = function (e) {
		if (e.altKey) {
		  window.open(el.href+'&full', '_blank');
		}
	  }
	});
}
video_display("date","desc");
</script>

<?php
}
else
{
	echo '<h4 style="margin-top: 0; margin-left: 2em;">Aucun enregistrement</h4>';
}
?>
<div style="clear:both;">&nbsp;</div>
</div>
</body>
</html>
