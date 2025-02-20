<?PHP

error_reporting(E_ERROR|E_CORE_ERROR|E_ALL|E_COMPILE_ERROR);
ini_set('display_errors', 'On');

// require_once ( "php/common.php" ) ;
require_once ( 'php/ToolforgeCommon.php' ) ;

function pretty ( $s ) {
	$s = str_replace ( '_' , ' ' , $s ) ;
	$s = htmlspecialchars($s);
	return $s ;
}

$tfc = new ToolforgeCommon('catnap');

$category = $tfc->getRequest ( 'category' , '' ) ;
$language = $tfc->getRequest ( 'language' , 'en' ) ;
$project = $tfc->getRequest ( 'project' , 'wikipedia' ) ;
$ignore = $tfc->getRequest ( 'ignore' , '' ) ;
$min_group = $tfc->getRequest ( 'min_group' , 5 ) ;
$menu = $tfc->getRequest ( 'menu' , false ) ;

print $tfc->getCommonHeader ( 'CatNap' ) ;

if ( $category == '' or $menu ) {
	print "<div>Lists Wikipedia articles in a category grouped by their other categories</div>
<form method='get' action='?' class='form-inline'>
<table class='table table-condensed'>
<tr><th>Project</th><td><input class='span2' type='text' name='language' value='".htmlspecialchars($language)."' /> . 
<input class='span3' type='text' name='project' value='".htmlspecialchars($project)."' /></td></tr>
<tr><th>Category</th><td><input type='text' name='category' value='".htmlspecialchars($category)."' /></td></tr>
<tr><th>Min group</th><td><input type='text' name='min_group' value='".htmlspecialchars($min_group)."' /></td></tr>
<tr><th>Ignore<br/>categories</th><td><textarea style='width:100%' name='ignore' cols='50' rows='10'>".htmlspecialchars($ignore)."</textarea></td></tr>
<tr><td></td><td><input class='btn btn-primary' type='submit' name='doit' value='Do it' /></td></tr>
</table>
</form>" ;
	exit ;
}

$ignore = explode ( "\n" , str_replace ( ' ' , '_' , str_replace ( "\r" , '' , $ignore ) ) ) ;

$db = $tfc->openDB ( $language , $project ) ;


$pages = [];
$page_listed = [];

$category_safe = $db->real_escape_string ( $category ) ;
$sql = "SELECT page_id,page_title FROM page,categorylinks WHERE cl_to=\"$category_safe\" AND cl_from=page_id LIMIT 1000" ;
$result = $tfc->getSQL($db,$sql);
while($o = $result->fetch_object()){
	$pages[$o->page_id] = $o ;
	$page_listed[$o->page_id] = false ;
}


$cft = [];
$cats = [];

if ( $tfc->use_new_categorylinks ) {
	$sql = "SELECT categorylinks.*,lt_title AS cl_to FROM categorylinks,linktarget WHERE cl_target_id=lt_id AND lt_namespace=0 AND cl_from IN (" . implode ( ',' , array_keys ( $pages ) ) . ")" ;
} else {
	$sql = "SELECT * FROM categorylinks WHERE cl_from IN (" . implode ( ',' , array_keys ( $pages ) ) . ")" ;
}
$result = $tfc->getSQL($db,$sql);
while($o = $result->fetch_object()){
	$cft[] = $o ;
	if ( !isset ( $cats[$o->cl_to] ) ) $cats[$o->cl_to] = [];
	$cats[$o->cl_to][] = $o->cl_from ;
}

foreach ( $ignore AS $i ) {
	if ( isset ( $cats[$i] ) )  unset ( $cats[$i] ) ;
}

unset ( $cats[$category] ) ;
foreach ( $cats AS $k => $v ) {
	if ( count ( $v ) < $min_group ) unset ( $cats[$k] ) ;
}

ksort ( $cats ) ;

print "<h1>" . pretty ( $category ) . "</h1>" ;

print "<div style='float:right;border:1px solid black;padding:2px;'>" ;
foreach ( $cats AS $k => $v ) {
	foreach ( $v AS $pk ) {
		$title = $pages[$pk]->page_title ;
		$page_listed[$pk] = true ;
	}
	$n = count ( $v ) ;
	print "<a href=\"#$k\">" . pretty ( $k ) . "</a> ($n)<br/>" ;
}
$other = 0 ;
foreach ( $page_listed AS $k => $v ) {
	if ( !$v ) $other++ ;
}
foreach ( $page_listed AS $k => $v ) $page_listed[$k] = false ;
print "<a href=\"#_other\">Other pages</a> (".htmlspecialchars($other).")" ;
print "</div>" ;

foreach ( $cats AS $k => $v ) {
	print "<h2><a name='".htmlspecialchars($k)."' href=\"http://".htmlspecialchars($language).".wikipedia.org/wiki/Category:".htmlspecialchars($k)."\">" . pretty ( $k) . 
			"</a> (<a href=\"?category=".htmlspecialchars($k)."&language=".htmlspecialchars($language)."\">CatNap</a>)</h2><ol>" ;
	$out = array () ;
	foreach ( $v AS $pk ) {
		$title = $pages[$pk]->page_title ;
		$page_listed[$pk] = true ;
		$out[$title] = "<li><a href=\"http://$language.wikipedia.org/wiki/".htmlspecialchars($title)."\">" . pretty ( $title ) . "</a></li>" ;
	}
	ksort ( $out ) ;
	print implode ( "\n" , $out ) ;
	print "</ol>" ;
}

print "<h2><a name='_other'>Other pages</a></h2><ol>" ;
$out = array () ;
foreach ( $page_listed AS $k => $v ) {
	if ( $v ) continue ;
	$title = $pages[$k]->page_title ;
	$out[$title] = "<li><a href=\"http://$language.wikipedia.org/wiki/".htmlspecialchars($title)."\">" . pretty ( $title ) . "</a></li>" ;
}
ksort ( $out ) ;
print implode ( "\n" , $out ) ;
print "</ol>" ;

print $tfc->getCommonFooter() ;

# Logging
$tfc->logToolUse() ;

?>