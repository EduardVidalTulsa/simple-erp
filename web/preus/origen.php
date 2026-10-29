<!DOCTYPE html>
<html>
  <head>
  </head>
  <body> 
    <?php
    require('login.php');
    if ($login) {	
      estil_capcal("Modificar origen","./origen.php",true,"$uri/sisu/estil.css");
	  
    echo" <form method='get' action='{$_SERVER['PHP_SELF']}'>";
    echo"  <label for='cerca'>Cerca:</label>";
    echo"  <input type='text' name='cerca' id='cerca'>";
    echo"  <input type='submit' value='Cercar'>";
   echo" </form>";
   
      echo "<table>";
      echo "<tr>";
      echo "<th>ID</th>";
      echo "<th>Producte</th>";
      echo "<th>Origen</th>";
      echo "<th>Modificar origen</th>";
      echo "</tr>";
      
      $where_clause = "WHERE lang='ca'";
$search_term = '';
if (!empty($_GET['cerca'])) {
  $search_term = $_GET['cerca'];
  $where_clause = "WHERE lang='ca' and (ID LIKE '%$search_term%' OR value LIKE '%$search_term%')";
}
$q = "SELECT ID, value, origen, lang FROM productnamesurlencoded $where_clause ORDER BY ID";
      $i = 0;
      $d = mysqli_query($link, $q) or die(errorr($q . mysqli_error($link)));
      while ($c = mysqli_fetch_row($d)) {
          echo "<tr id=" . $c[0] . ">";
          echo "<td>" . $c[0] . "</td>";
          echo "<td>" . $c[1] . "</td>";
          echo "<td>" . $c[2] . "</td>";
          echo "<td>";
          // Formulari per modificar l'origen del producte
          echo "<form method='post' action='modificar_origen.php'>";
          echo "<input type='hidden' name='id' value='" . $c[0] . "' />";
          echo "<input type='text' name='origen' value='" . $c[2] . "' />";
          echo "<input type='submit' value='Modificar' />";
          echo "</form>";
          echo "</td>";
          echo "</tr>";
          $i++;
      }
      echo "</table>";
      $i--;
      h3c("He mostrat $i productes");
    }else{header("Location: index.php");}
    ?>
  </body>
</html>