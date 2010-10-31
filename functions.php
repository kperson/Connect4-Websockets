<?php
function winner($data){
    $grid = $data['grid'];
    $size = $data['size'];
    for($r = 0; $r < 7; $r++){
        for($c = 0; $c < 7; $c++){
            $rs = checkDirection($r, $c, 1, 1, $grid);
            if($rs != -1){
                break;
            }
            $rs = checkDirection($r, $c, -1, -1, $grid);
            if($rs != -1){
                break;
            }
            $rs = checkDirection($r, $c, 0, 1, $grid);
            if($rs != -1){
                break;
            }
            $rs = checkDirection($r, $c, 1, 0, $grid);
            if($rs != -1){
                break;
            }
        }
        if($rs != -1){
            break;
        }
    }
    if($rs != -1){
        return $rs;
    }
    else if($size != 49){
        return 0;
    }
    return $rs;
}

function checkDirection($r, $c, $x, $y, $grid){
    if($grid[$r][$c] != -1){
        $ct = 0;
        while($ct < 4){
            $my_x = $ct * $x + $r;
            $my_y = $ct * $y + $c;
            if($my_x > 6 || $my_y > 6 || $my_y < 0 || $my_x < 0){
                return -1;
            }
            if( $grid[$my_x][$my_y] != $grid[$r][$c]){
                return -1;
            }
            $ct++;
        }

        return $grid[$r][$c];
    }
    return -1;
}
?>