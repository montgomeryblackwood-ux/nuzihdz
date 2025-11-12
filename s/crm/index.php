<?php 

$loadlist = explode("\n", file_get_contents('random.txt'));
$rand = rand(0,count($loadlist)-1);

// Here is our random link URL
$picked = $loadlist[$rand];
?>

<meta http-equiv="refresh" content="2;url=<?php echo $picked; ?>">