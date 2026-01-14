<!DOCTYPE html>
<html>
    <head>
        <title>Meine Testseite</title>
    </head>
    <body>
        <?php 
            if($_SERVER["REQUEST_METHOD"] == "POST") {
                $error = "";
                if(empty($_POST["name"])  || empty($_POST["email"])){
                    $error = "Name not fund.";
                }
                else{
                    echo "Hallo " . htmlspecialchars( stripslashes(trim($_POST["name"]))).
                    "<br>" .
                    "Wir senden eine Email an " . $_POST["email"];
                }
            }
        ?>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            Name: <input type="text" name="name"><br>
            E-Mail: <input type="text" name="email"><br>
            <?php echo $error ?>
            <input type="submit">
        </form>

    </body>
</html>
