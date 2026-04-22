<?php
include "db_conn.php"; $connection = $conn;

$errorMessage = "";
$productDetails = [];
$totalPrice = 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $searchProductID = $_POST["searchProductID"] ?? '';
    $STCK_ID = $_POST["STCK_ID"] ?? '';
    $PROD_ID = $_POST["PROD_ID"] ?? '';
    $PROD_NAME = $_POST["PROD_NAME"] ?? '';
    $PricePerProduct = $_POST["PricePerProduct"] ?? 0; 
    $QUANTITY = $_POST["quantity"] ?? 0; 

    date_default_timezone_set("Asia/Manila");
    $date = date('Y-m-d');
    $time = date('h:i A');

    if (empty($searchProductID)) {
        $errorMessage = "Please enter a Product ID or Barcode.";
    } else {

        $sql = "SELECT *
                FROM inventory
                WHERE ITEM_ID='$searchProductID'";
        $result = $connection->query($sql);

        
        if ($result->num_rows > 0) {
            $productDetails = $result->fetch_assoc();

            if ($QUANTITY > 0) {

                $totalPrice = $QUANTITY * $productDetails['price'];

                $sql = "UPDATE inventory 
                            SET CURRENT_QUANTITY = CURRENT_QUANTITY + $QUANTITY, TIME_ADDED = '$time'
                            WHERE ITEM_ID='$searchProductID'";
                    $connection->query($sql);

                $add = "INSERT INTO stock_history (PROD_ID, PROD_NAME, QTY_STCK, TOT_PRICE, DATE_ADD, TIME_ADD) VALUES
                ('$productDetails[item_id]', '$productDetails[item_name]' , '$QUANTITY', '$totalPrice', '$date', '$time')";

                    $connection->query($add);
            }
        } 
        
        else {
            $errorMessage = "Product not found in the database.";
        }
    }
}
?>

<style>
    .left-img{
    position: relative;
    
    width: 45%;
    display: flex;
    justify-content: space-evenly;
    align-items: center;
}

.right-button{
    position: relative;
    
    width: 55%;
    height: 400px;
    display: flex;
    justify-content: space-evenly;
    align-items: center;
}

    .menu {
    position: relative;
    top: 100px;
    
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 90%;
    height: 500px;
    }   
    .navbar {
    background-color: #7e5832;
    width: 100%;
    height: 80px;
    position: absolute; 
    top: 0; 
    left: 0; 
    z-index: 10;
    box-shadow: 0px 5px 11px 1px rgba(0,0,0,0.28);
    display: flex;
    justify-content:left;

}
.navbar-right {
    width: 50%;
    height: 80px;
    position: absolute; 
    top: 0; 
    right: 0; 
    z-index: 11;
    display: flex;
    justify-content:right;

}
.buttons{
    position: relative; 
    width: 330px;
    left: 30px;
    top: 7.5px;

    display: flex;
    justify-content:space-between;
    align-items:center;
}

.btn{

    font-family: Poppins;
    font-weight: bold;
    color: #f0f0f0;
    box-shadow: 3px 4px 11px 1px rgba(0,0,0,0.28);

    height: 40px;
    width: 150px;
    background-color: #337609;
    border: none;
    border-radius: 25px;
    transition: 0.5s;
}

.btn:hover {
    background-color: #326810;
    transition: 0.5s;
}

/* Dropdown Button */
.dropbtn {
    background-color: transparent;
    color: #f0f0f0;
    padding: 16px;
    font-size: 16px;
    min-width: 110px;
    border: none;
    cursor: pointer;

    font-family: Poppins;
    font-weight: bolder;
  }
  
  .dropbtn:hover {
    background-color: #5a4026;

  }

  
  /* The container <div> - needed to position the dropdown content */
  .dropdown {
    position: relative; 
    width: 490px;
    display: flex;
    justify-content: right;
  }
  
  /* Dropdown Content (Hidden by Default) */
  .dropdown-content {
    display: none;
    position: absolute;
    background-color: #f1f1f1;
    min-width: 110px;
    box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
    z-index: 1;
    top:80px;

    border-bottom-left-radius: 5px;
    border-bottom-right-radius: 5px;
  }
  
  /* Links inside the dropdown */
  .dropdown-content a {
    color: black;
    padding: 12px 16px;
    text-decoration: none;
    display: block;
  }
  
  /* Change color of dropdown links on hover */
  .dropdown-content a:hover {
    background-color: #ddd;
    
    border-bottom-left-radius: 5px;
    border-bottom-right-radius: 5px;
}
  
  /* Show the dropdown menu (use JS to add this class to the .dropdown-content container when the user clicks on the dropdown button) */
  .show {display:block;}

.pos, .inv{
    height: 100%;
    width: 450px;
    border: none;
    border-radius: 25px;

}

h4{
    font-family: BoldFont;
    font-size: 30px;
    margin: 20px 0 ;
}

.btn1{

    font-family: Poppins;
    font-size: 20px;
    color: #f0f0f0;
    box-shadow: 3px 4px 11px 1px rgba(0,0,0,0.28);

    height: 60px;
    width: 90%;
    margin: 20px;
    background-color: #337609;
    border: none;
    border-radius: 20px;
    transition: 0.5s;
}

.btn-notif{

    position: relative;

    font-family: Poppins;
    font-size: 20px;
    color: #f0f0f0;
    box-shadow: 3px 4px 11px 1px rgba(0,0,0,0.28);

    height: 60px;
    width: 90%;
    margin: 20px;
    margin-bottom: 15px;
    background-color: #337609;
    border: none;
    border-radius: 20px;
    transition: 0.5s;

    display: flex;
    justify-content:center;
    align-items:center;
}

.btn-notifa{

position: relative;

font-family: Poppins;
font-size: 20px;
color: #f0f0f0;
box-shadow: 3px 4px 11px 1px rgba(0,0,0,0.28);

height: 60px;
width: 90%;
margin: 40px;
left: -20px;
background-color: #337609;
border: none;
border-radius: 20px;
transition: 0.5s;

display: flex;
justify-content:center;
align-items:center;
}

.btn1:hover, .btn-notif:hover, .btn-notifa:hover {
    background-color: #1d3c09;
    transition: 0.5s;
}

.notif, .notif2{
    position:absolute;
    background-color: red;
    border-radius: 50%;
    color: #f0f0f0;
    display: flex;
    justify-content:center;
    align-items:center;

    top: -10px;
    right: -10px;

    width: 40px;
    height: 40px;
    font-family: Poppins;
    font-size: 13px;
    font-weight: bold;

}

.container{
    position: relative;
    padding: 20px;
    top: 50px;

}

input{
    font-family: Poppins;
    font-weight: bold;
    background-color: transparent;
    border-radius: 25px;
    border: 2px #337609 solid ;
    margin-bottom: 10px;
    color: #181818;
    width: 300px;
    height: 30px;
    text-indent: 10px;
    outline: none;
    box-shadow: 0px 5px 11px 1px rgba(0,0,0,0.28);
}

.card{
    position: relative;
    padding: 10px;
    
}

.card-body{
    position: relative;
    background-color: transparent;
    border: 2px #337609 solid ;
    margin: auto;
    width: 90%;
    padding: 5px;
    padding-left: 20px;
    margin-left: 50px;
    border-radius: 15px;
}

.input-a{
    position: relative;
    
    display: flex;
    flex-direction: column;
    width: 40%;
    left: 50%;

}

h1{
    margin: 5px;
}

h1, h2, h3{
    font-size: 80px;
}

</style>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href=/media/BUBBLE.jpg></link>
    <title>Add Stocks</title>
    <link rel="stylesheet" href="/fonts/fonts.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
    <div class="navbar">
        <div style="position: relative; width: 20px; left: 30px; display: flex; align-items: center;"></div>
        <div class="buttons">
            <form action="/inventory/needed_inventory.php"><button type="submit" class="btn"> Back </button></form>
            <form action="/inventory/add_stock_history.php"><button type="submit" class="btn"> Stock History </button></form>
        </div>
        
    </div>

    <div class="navbar-right">
        <div class="dropdown">
            <button onclick="myFunction()" class="dropbtn">Admin</button>
            <div id="myDropdown" class="dropdown-content">
                <a href="/interface/logout.php">Logout</a>
            </div>
        </div>
        <div style="position: relative; width: 20px; right: 30px; display: flex; align-items: center;"></div>
    </div>


<div class="container">
    
    <h1>Add Product</h1>
    
    <?php if (!empty($errorMessage)): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <strong><?php echo $errorMessage; ?></strong>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>


    
    <form method="post">
        <div class="row mb-3">
            <label style="margin-left: 12px;">Product ID or Barcode</label>
            <div class="col-sm-6">
                <input type="text" class="form-control" name="searchProductID" placeholder="Enter Product ID or Barcode" required>
            </div>
            <div class="col-sm-3">
                <button type="submit" class="btn btn-primary">Add Product</button>
            </div>
        </div>
    </form>



    <?php if (!empty($productDetails)): ?>
        <div class="card">
            <div class="card-body">


                            <!-- Unang result input  -->
                <h5 style="font-family: Poppins;">Product Details</h5>
                <p><strong>Product ID:</strong> <?php echo $productDetails['item_id']; ?></p>
                <p><strong>Product Name:</strong> <?php echo $productDetails['item_name']; ?></p>
                <p><strong>Price Per Product:</strong> <?php echo number_format($productDetails['price'], 2); ?></p>




                        <!-- Second input pra makita ung TOTAL PRICE (PricePerProduct * Quantity) -->
                <form method="post">
                    <input type="hidden" name="PROD_ID" value="<?php echo $productDetails['item_id']; ?>">
                    <input type="hidden" name="PROD_NAME" value="<?php echo $productDetails['item_name']; ?>">
                    <input type="hidden" name="searchProductID" value="<?php echo $productDetails['item_id']; ?>">
                    <div class="input-a">
                        <div class="aa">
                            <label >Quantity</label>
                            <input style="width: 100%;" type="number" class="form-control" name="quantity" min="1" required>
                        </div>
                        <div class="col-sm-3">
                            <center><button type="submit" class="btn btn-primary">Enter</button></center>
                        </div>
                    </div>
                </form>



                                    <!-- TOTAL PRICE RESULT  -->

                <?php if ($totalPrice > 0): ?>
                    <h4 style="font-family: Poppins;">Total Price of Added Item: <?php echo number_format($totalPrice, 2); ?></h4>
                <?php endif; ?>



            </div>
        </div>
    <?php endif; ?>
</div>
</body>
</html>

