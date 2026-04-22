<?php
session_start();


include "db_conn.php";
$connection = $conn;


// Get Total from Completed Orders
global $total;
global $sql_chiken;

$total_sale = "SELECT total FROM customer_orders
               WHERE status = 'Completed'";
$total_sale = $connection->query($total_sale);

if (!$total_sale) {
  die("Invalid query: " . $connection->error);
}

while ($row = mysqli_fetch_array($total_sale)) {
  $total += $row['total'];
}

// Handle form submit
if (isset($_POST['add_chicken'])) {
  $order_id = $_POST['order_id'];
  $quantity = $_POST['quantity'];
  $flavor = $_POST['flavor'];

  // Insert into order items
  $sql = "INSERT INTO customer_order_items (order_id, menu_item_id, size_id, quantity, price, flavor, created_at)
            VALUES ('$order_id', 'CHICKEN', 'REG', '$quantity', '', '$flavor', NOW())";

  // <CHANGE> Add inventory decrement
  $sql_inventory = "UPDATE unified_inventory 
                    SET CURRENT_QUANTITY = CURRENT_QUANTITY - $quantity 
                    WHERE ITEM_NAME = 'Chicken Wings'";

  if ($connection->query($sql) === TRUE && $connection->query($sql_inventory) === TRUE) {
    echo "<script>alert('Chicken added successfully!');</script>";
  } else {
    echo "<script>alert('Error adding chicken: " . $connection->error . "');</script>";
  }
}
if (isset($_POST['confirm_payment'])) {
  $order_id = $_POST['order_id'];
  $amount_paid = $_POST['amount_paid'];

  $sql = "UPDATE customer_orders 
            SET amount_paid = '$amount_paid'
            WHERE order_id = '$order_id'";

  if ($connection->query($sql) === TRUE) {
    echo "<script>alert('Payment recorded successfully!');</script>";
  } else {
    echo "<script>alert('Error updating payment: " . $connection->error . "');</script>";
  }
}


if (isset($_SESSION['ACC_ID']) && isset($_SESSION['EMAIL'])) { 


 // <CHANGE> Add database query to get logged-in user's name
    $sql = "SELECT fname, lname FROM accounts WHERE ACC_ID = " . $_SESSION['ACC_ID'];
    $result = $connection->query($sql);
    
    if($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $fname = htmlspecialchars($user['fname']);
        $lname = htmlspecialchars($user['lname']);
        $full_name = $fname . " " . $lname;
    } else {
        $full_name = "Employee";
    }


?>

  <!DOCTYPE html>
  <html lang="en">

  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="icon" href=/media/BUBBLE.jpg>
    
    <link rel="stylesheet" href="/fonts/fonts.css">


    <title>Employee Interface</title>
  </head>

  <style>
    .navbar {
      background-color: #7e5832;
      width: 100%;
      height: 80px;
      position: absolute;
      top: 0;
      left: 0;
      z-index: 10px;
      box-shadow: 0px 5px 11px 1px rgba(0, 0, 0, 0.28);
      display: flex;
      justify-content: left;

    }

    .navbar-right {
      width: 50%;
      height: 80px;
      position: absolute;
      top: 0;
      right: 0;
      z-index: 11;
      display: flex;
      justify-content: right;

    }

    .buttons {
      position: relative;
      width: 330px;
      left: 30px;

      display: flex;
      justify-content: left;
      gap: 20px;
      align-items: center;
    }

    .btn {

      font-family: Poppins;
      font-weight: bold;
      color: #f0f0f0;
      box-shadow: 3px 4px 11px 1px rgba(0, 0, 0, 0.28);

      height: 40px;
      width: 150px;
      font-size: 0.85rem;
      background-color: #337609;
      border: none;
      border-radius: 25px;
      transition: 0.5s;
    }

    .btn-endsale {
      position: absolute;
      font-family: Poppins;
      font-weight: bold;
      color: #f0f0f0;
      box-shadow: 3px 4px 11px 1px rgba(0, 0, 0, 0.28);

      height: 40px;
      width: 150px;
      right: 20px;
      bottom: 30px;
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
      border: none;
      cursor: pointer;

      font-family: Poppins;
      font-weight: bolder;
    }

    .dropbtn:hover,
    .dropbtn:focus {
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
      box-shadow: 0px 8px 16px 0px rgba(0, 0, 0, 0.2);
      z-index: 1;
      top: 80px;

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


    .show {
      display: block;
    }

    .main {
      position: relative;
      width: 100%;
      height: 80vh;
      top: 60px;
      display: flex;
      flex-direction: column;
    }

    h1 {
      margin: 5px;
    }

    .sales-summary {
      position: relative;
      padding: 10px;
      display: flex;
      justify-content: left;
      align-items: center;

    }

    .sales-bottom {
      position: relative;
      height: 100%;
      padding: 10px;
      display: flex;
      justify-content: space-evenly;
      align-items: center;


    }

    .left,
    .right {
      background-color: white;
      border: #337609 3px solid;
      box-shadow: 0px 8px 16px 0px rgba(0, 0, 0, 0.2);
      border-radius: 20px;
      width: 48%;
      height: 100%;
      margin: 5px;
    }

    .scroll {
      overflow: scroll;
      overflow-x: hidden;
      height: 300px;
    }

    th,
    td {
      padding: 10px;
    }

    table {
      position: relative;
      width: 100%;

      margin: auto;
      border-collapse: collapse;
    }

    tr {
      position: relative;
      border-bottom: 1px solid #ccc;
      padding: 20px;
      margin: 5px;

    }

    th {
      text-align: left;
    }

    .input-group {
      padding: 10px;
      display: flex;
      margin-left: 40px;
    }

    .form-outline {
      width: 80%;
    }

    input {
      font-family: Poppins;
      font-weight: bold;
      background-color: transparent;
      border-radius: 25px;
      border: 2px #337609 solid;
      margin-bottom: 10px;
      color: #181818;
      height: 30px;
      text-indent: 10px;
      outline: none;
      box-shadow: 0px 5px 11px 1px rgba(0, 0, 0, 0.28);
    }

    .btn-edit {
      text-decoration: none;

      position: relative;
      background-color: rgb(91, 149, 91);
      padding: 3px;
      border-radius: 5px;
      color: #f0f0f0;
      border-color: transparent;
      margin: 5px;
      font-family: Poppins;
      cursor: pointer;
    }

    .btn-edit:hover {
      background-color: rgb(79, 128, 79);
    }

    .btn-dgr {
      text-decoration: none;
      background-color: rgb(91, 149, 91);
      padding: 3px;
      border-radius: 5px;
      color: #f0f0f0;
      border-color: transparent;
      font-family: Poppins;
      cursor: pointer;
      transition: 0.5s;
    }

    .btn-dgr:hover {
      background-color: rgb(79, 128, 79);
      transition: 0.5s;
    }

    .a {
      position: fixed;
      width: 10%;
      height: 10%;
      z-index: 15;
      left: 25%;
      top: 30%;

    }

    .dropdown-ver {
      display: none;
      position: fixed;
      background-color: #f1f1f1;
      width: 45.5%;
      padding: 30px;
      margin: auto;
      box-shadow: 0px 8px 16px 0px rgba(0, 0, 0, 0.2);
      z-index: 1;
      top: 200px;

      border-radius: 15px;

    }

    .b {
      display: flex;
      justify-content: space-evenly;
    }

    .a1 {
      background-color: rgb(91, 149, 91);
      display: inline;
      color: #f0f0f0;
      width: 100px;
      border-radius: 15px;
      text-align: center;
      text-decoration: none;

      justify-content: center;
    }

    .b1 {
      background-color: firebrick;
      display: inline;
      color: #f0f0f0;
      width: 100px;
      border-radius: 15px;
      text-align: center;
      text-decoration: none;

      justify-content: center;
    }

    .show1 {
      display: block;
    }

    .rem1 {
      display: none;
    }

    .scroll {
      overflow: scroll;
      overflow-x: hidden;
      height: 70%;
    }

    h1,
    h2,
    h3 {
      font-size: 80px;
    }

    .clock {
      font-size: 20px;
      color: white;
      font-weight: bold;
      font-family: Poppins;
      position: relative;
      width: auto;
      left: 37%;
      display: flex;
      align-items: center;

    }

    .modal {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.4);
      justify-content: center;
      align-items: center;
      z-index: 999;
    }

    .modal-content {
      background: #fff;
      padding: 20px;
      border-radius: 10px;
      width: 300px;
      text-align: center;
    }

    .modal-content input,
    .modal-content select {
      width: 100%;
      margin: 8px 0;
      padding: 6px;
      border-radius: 5px;
      border: 1px solid #ccc;
    }

    .modal-actions button {
      margin: 5px;
      padding: 6px 12px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
    }

    .flex-container {

      display: flex;

      justify-content: center;
      /* flex-flow: row nowrap; */
      flex-direction: row;
      flex-wrap: nowrap;
      align-content: stretch;

      height: 100%;
      width: 100%;
      padding: 15px;
      gap: 10px;

    }

    .flex-container>div {
      background: white;
      border: 3px solid #326810;
      border-radius: 5px;
      padding: 8px;
    }


    .item1 {
      /* flex:0 1 auto; */
      order: 1;
      width: 60%;
    }

    .item2 {
      /* flex:0 1 auto; */
      order: 2;
      width: 40%;
    }

    .btn-edita {
      text-decoration: none;
      text-align: center;
      position: relative;
      background-color: rgb(91, 149, 91);
      padding: 3px;
      border-radius: 5px;
      color: #f0f0f0;
      border-color: transparent;
      margin: 5px;
      font-family: Poppins;
      cursor: pointer;
    }

    #right {
      text-align: right;
    }

    #chicken {
      background-color: #f29c11;
    }

    #viewRec {
      background-color: #2e9ae1;
    }

    /* --- Mobile responsiveness --- */
    @media (max-width: 768px) {
      .flex-container {
        flex-direction: column;
        /* stack items vertically */
        flex-wrap: wrap;
      }

      .item1,
      .item2 {
        width: 94%;
        /* full width on tablet/mobile */
      }

      .main {
        height: auto;
        /* let main shrink to content */
        top: 50px;
        /* adjust top spacing if needed */
        padding: 10px;
        gap: 5px;
      }

      .flex-container>div {
        padding: 6px;
        border-width: 2px;
      }

      .btn {
        width: 100px;
        height: 30px;
        font-size: 10px;
        border-radius: 18px;
      }
    }
    
    
.welcome-float {
            position: fixed;
            top: 50px;
            left: 50%;
            transform: translateX(-50%);
            background-color: #1d3c09;
            color: white;
            padding: 15px 30px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            z-index: 9999;
            font-size: 16px;
            font-weight: 500;
            animation: fadeOut 5s ease-in-out forwards;
        }
        
        .welcome-float p {
            margin: 0;
            padding: 0;
        }
  </style>

  <body>
      
      <div id="welcome-notification" class="welcome-float">
            <p>Welcome <?php echo $full_name; ?></p>
        </div>
    <!-- Complete Order -->
    <div class="a">
      <div class="dropdown-ver" id="del-drop">
        <div class="body">
          <p>Order Completion</p>
          <form method="GET" action="/pointofsale/complete_order.php" id="form-delete-user">
            <div>
              <p>Are you sure the ORDER is finished? </p>
              <input type="hidden" style="width: 50%; border-radius: 5px;" name="id">
            </div>
          </form>
        </div>
        <div class="b">
          <button type="button" style="display:inline-block; width: 100px; align-items:center" class="btn-edit"
            onclick="confirmDel(this)">No, Not Yet</button>
          <button type="submit" style="display:inline-block; width: 100px; align-items:center" form="form-delete-user"
            class="btn-edit">Yes, Its Finished</button>

        </div>
      </div>
    </div>

    <div id="chickenModal" class="modal">
      <div class="modal-content">
        <form method="POST" onsubmit="return validateChickenForm()">
          <label>Add Chicken</label>
          <input type="hidden" id="order_id" name="order_id">

          <label>Quantity (3–5):</label>
          <input type="number" id="quantity" name="quantity" min="3" max="5" required
            style="font-family:Poppins; width:96%;">

          <label>Flavor:</label>
          <select id="flavor" name="flavor" required style="font-family:Poppins;">
            <option value="" disabled selected>Select Flavor</option>
            <option value="Garlic Parmesan">Garlic Parmesan</option>
            <option value="Buffalo">Buffalo</option>
            <option value="Honey BBQ">Honey BBQ</option>
            <option value="Korean Soy">Korean Soy</option>
          </select>

          <div class="modal-actions">
            <button type="submit" name="add_chicken" class="btn-edit">Add</button>
            <button type="button" onclick="closeModal()" class="btn-edit">Cancel</button>
          </div>
        </form>
      </div>
    </div>

    <div id="paymentModal" class="modal">
      <div class="modal-content">
        <form method="POST">
          <input type="hidden" id="pay_order_id" name="order_id">

          <label>Enter Payment Amount</label>
          <input type="number" name="amount_paid" id="amount_paid" min="0" step="0.01" required
            style="font-family:Poppins; width:96%;">

          <div class="modal-actions">
            <button type="submit" name="confirm_payment" class="btn-edit">Confirm</button>
            <button type="button" onclick="closePayModal()" class="btn-edit">Cancel</button>
          </div>
        </form>
      </div>
    </div>



    <div class="navbar">
      <div style="position: relative; width: 20px; left: 30px; display: flex; align-items: center;"></div>
      <div class="logo"><img src="/media/BUBBLE.jpg" width="80px"></div>
      <div class="buttons">
        <form action="/pointofsale/indeck.php"><button type="submit" class="btn"> Point of Sale </button></form>
        <form action="/pointofsale/order_screen.php"><button type="submit" class="btn"> Incoming Orders </button>
        </form>
      </div>

    </div>

    <div class="navbar-right">
      <div id="clock" class="clock"></div>
      <div class="dropdown">
        <button onclick="myFunction()" class="dropbtn">Employee</button>
        <div id="myDropdown" class="dropdown-content">
          <a href="logout.php">Logout</a>
        </div>
      </div>
      <div style="position: relative; width: 20px; right: 30px; display: flex; align-items: center;"></div>
    </div>

    <br>

    <div class="main">
      <div class="sales-summary">
        <h1 style="font-size: 60px;">Total Sale: Php <?php echo '' . number_format($total, 2, '.', ','); ?></h1>
        <form action="/interface/get_sales.php?id="><button type="submit" class="btn-endsale"> End Sale </button>
        </form>
      </div>

      <div class="sales-bottom">

        <div class="flex-container">

          <div class="item1">

            <center>
              <h1 style="font-size: 30px; margin: 20px;">Order Status</h1>
            </center>

            <div class="scroll">

              <table class="table table-bordered table-striped">
                <thead>
                  <tr>
                    <th>
                      <center>Order ID</center>
                    </th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>
                      <center>
                        Options
                      </center>
                    </th>

                  </tr>
                </thead>
                <tbody>
                  <?php
                  $connection = $conn;



                  $sql_returned_goods = "
                                                SELECT co.*, 
                                                IF(GROUP_CONCAT(coi.menu_item_id) LIKE '%UNLICHICKENWINGS%', 'UNLICHICKENWINGS', coi.menu_item_id) as menu_item_id
                                                FROM customer_orders AS co
                                                JOIN customer_order_items AS coi ON coi.order_id = co.order_id
                                                WHERE co.status IN ('Order Ready', 'Pending')
                                                GROUP BY co.order_id
                                                ORDER BY co.status DESC
                                                                                            ";
                  $result_returned_goods = $connection->query($sql_returned_goods);



                  if (!$result_returned_goods) {
                    die("Invalid query: " . $connection->error);
                  }


                  while ($row = $result_returned_goods->fetch_assoc()) {

                    //UNLI CHICKEN ACTIONS
                    if ($row['menu_item_id'] == 'UNLICHICKENWINGS' && substr($row['order_id'], 0, 3) == 'ORD') {


                     if($row['status'] == 'Order Ready'){
                          echo "
                                <tr>
                                    <td><center>{$row['order_id']}</center></td>
                                    <td>{$row['total']}</td>
                                    <td>{$row['status']}</td>
                                    <td id='right'>
                                    
                                    <a class='btn-edita' style='display:inline-block; width:60px; height: 30px; font-size: 10px;' data-id='$row[order_id]' onclick='confirmDel(this)'>Complete Order</a>
                                    <a class='btn-edita' style='display:inline-block; width:60px; height: 30px; font-size: 10px;' data-id='$row[order_id]' onclick='openModal(this)' id='chicken'>Add Chicken</a>
                                    <a class='btn-edita' style='display:inline-block; width:60px; height: 30px; font-size: 10px;' href='/pointofsale/view_receipt_home.php?order_id=$row[order_id]' id='viewRec'>View Receipt</a>
                                    
                                    </td>
                                </tr>
                                ";
                     }
                     
                     else{
                          echo "
                                <tr>
                                    <td><center>{$row['order_id']}</center></td>
                                    <td>{$row['total']}</td>
                                    <td>{$row['status']}</td>
                                    <td id='right'>
                                    
                                    <a class='btn-edita' style='display:inline-block; width:60px; height: 30px; font-size: 10px;' data-id='$row[order_id]' onclick='openModal(this)' id='chicken'>Add Chicken</a>
                                    <a class='btn-edita' style='display:inline-block; width:60px; height: 30px; font-size: 10px;' href='/pointofsale/view_receipt_home.php?order_id=$row[order_id]' id='viewRec'>View Receipt</a>
                                    
                                    </td>
                                </tr>
                                ";
                     }

                    }

                    // QR ORDER ACTIONS
                    else if (substr($row['order_id'], 0, 3) == 'TBL') {
                        
                    //If Order Unli Chicken
                      if ($row['menu_item_id'] == 'UNLICHICKENWINGS') {
                       
                       if($row['status'] == 'Order Ready' && $row['amount_paid'] !== '0.00') {
                            echo "
                                    <tr>
                                        <td><center>{$row['order_id']}</center></td>
                                        <td>{$row['total']}</td>
                                        <td>{$row['status']}</td>
                                        <td id='right'>
                                        
                                        <a class='btn-edita' style='display:inline-block; width:60px; height: 30px; font-size: 10px;' data-id='$row[order_id]' onclick='confirmDel(this)'>Complete Order</a>
                                        
                                        <a class='btn-edita' style='display:inline-block; width:60px; height: 30px; font-size: 10px;' data-id='$row[order_id]' onclick='openModal(this)' id='chicken'>Add Chicken</a>
                                        <a class='btn-edita' style='display:inline-block; width:60px; height: 30px; font-size: 10px;' href='/pointofsale/view_receipt_home.php?order_id=$row[order_id]' id='viewRec'>View Receipt</a>
                                        
                                        </td>
                                    </tr>
                                    ";
                       }
                       
                       else {
                            echo "
                                    <tr>
                                        <td><center>{$row['order_id']}</center></td>
                                        <td>{$row['total']}</td>
                                        <td>{$row['status']}</td>
                                        <td id='right'>
                                        
                                        
                                        <a class='btn-edita' style='display:inline-block; width:60px; height: 30px; font-size: 10px;' data-id='$row[order_id]' onclick='confirmPay(this)'>Complete Payment</a>
                                        <a class='btn-edita' style='display:inline-block; width:60px; height: 30px; font-size: 10px;' data-id='$row[order_id]' onclick='openModal(this)' id='chicken'>Add Chicken</a>
                                        <a class='btn-edita' style='display:inline-block; width:60px; height: 30px; font-size: 10px;' href='/pointofsale/view_receipt_home.php?order_id=$row[order_id]' id='viewRec'>View Receipt</a>
                                        
                                        </td>
                                    </tr>
                                    ";
                       }
                       
                      } 
                        
                    // If Order any from QR
                      else {
                          if ($row['status'] == 'Order Ready' && $row['amount_paid'] !== '0.00'){
                              echo "
                                    <tr>
                                        <td><center>{$row['order_id']}</center></td>
                                        <td>{$row['total']}</td>
                                        <td>{$row['status']}</td>
                                        <td id='right'>
                                        
                                        <a class='btn-edita' style='display:inline-block; width:60px; height: 30px; font-size: 10px;' data-id='$row[order_id]' onclick='confirmDel(this)'>Complete Order</a>
                                        <a class='btn-edita' style='display:inline-block; width:60px; height: 30px; font-size: 10px;' href='/pointofsale/view_receipt_home.php?order_id=$row[order_id]'id='viewRec'>View Receipt</a>
                                        
                                        </td>
                                    </tr>
                                    ";
                              
                          }
                          
                          else{
                              echo "
                                    <tr>
                                        <td><center>{$row['order_id']}</center></td>
                                        <td>{$row['total']}</td>
                                        <td>{$row['status']}</td>
                                        <td id='right'>
                                        
                                        
                                        <a class='btn-edita' style='display:inline-block; width:60px; height: 30px; font-size: 10px;' data-id='$row[order_id]' onclick='confirmPay(this)'>Complete Payment</a>
                                        <a class='btn-edita' style='display:inline-block; width:60px; height: 30px; font-size: 10px;' href='/pointofsale/view_receipt_home.php?order_id=$row[order_id]'id='viewRec'>View Receipt</a>
                                        
                                        </td>
                                    </tr>
                                    ";
                          }
                        
                        
                        
                        
                        
                      }


                    }



                    //STANDARD POS ORDER ACTIONS
                    else {
                        
                        if ($row['status'] == 'Order Ready'){
                             echo "
                                <tr>
                                    <td><center>{$row['order_id']}</center></td>
                                    <td>{$row['total']}</td>
                                    <td>{$row['status']}</td>
                                    <td id='right'>
                                    
                                    <a class='btn-edita' style='display:inline-block; width:60px; height: 30px; font-size: 10px;' data-id='$row[order_id]' onclick='confirmDel(this)'>Complete Order</a>
                                   
                                    <a class='btn-edita' style='display:inline-block; width:60px; height: 30px; font-size: 10px;' href='/pointofsale/view_receipt_home.php?order_id=$row[order_id]' id='viewRec'>View Receipt</a>
                                    
                                    </td>
                                </tr>
                                ";
                        }
                        
                        else {
                             echo "
                                <tr>
                                    <td><center>{$row['order_id']}</center></td>
                                    <td>{$row['total']}</td>
                                    <td>{$row['status']}</td>
                                    <td id='right'>
                                   
                                    <a class='btn-edita' style='display:inline-block; width:60px; height: 30px; font-size: 10px;' href='/pointofsale/view_receipt_home.php?order_id=$row[order_id]' id='viewRec'>View Receipt</a>
                                    
                                    </td>
                                </tr>
                                ";
                        }
                     
                    }

                  }
                  ?>
                </tbody>
              </table>

            </div>



          </div>

          <div class="item2">
            <center>
              <h1 style="font-size: 30px; margin: 20px;">Limited Items</h1>
            </center>


            <div class="scroll">

              <table class="table table-bordered table-striped">
                <thead>
                  <tr>
                    <th>Product ID</th>
                    <th>Product Name</th>
                    <th>Quantity</th>

                  </tr>
                </thead>
                <tbody>
                  <?php
                  include "db_conn.php"; $connection = $conn;


                  $sql_returned_goods = " SELECT
                                                ITEM_NAME,
                                                CATEGORY,
                                                CURRENT_QUANTITY,
                                                COST_PER_UNIT
                                                FROM unified_inventory";
                  $result_returned_goods = $connection->query($sql_returned_goods);

                  if (!$result_returned_goods) {
                    die("Invalid query: " . $connection->error);
                  }


                  while ($row = $result_returned_goods->fetch_assoc()) {

                    $totalPrice = $row['COST_PER_UNIT'] * $row['CURRENT_QUANTITY'];

                    if ($row['CURRENT_QUANTITY'] >= 11) {
                      continue;
                    } else {
                      echo "
                                <tr>
                                    
                                    <td>{$row['ITEM_NAME']}</td>
                                    <td>{$row['CATEGORY']}</td>
                                    <td>{$row['CURRENT_QUANTITY']}</td>
                                </tr>
                                ";
                    }
                  }
                  ?>
                </tbody>
              </table>

            </div> <!--flex-container-->


          </div> <!--sales-bottom-->



        </div> <!--main-->
    <script>
    // Remove the notification after 5 seconds
    setTimeout(function() {
        var notification = document.getElementById('welcome-notification');
        if(notification) {
            notification.remove();
        }
    }, 5000);
</script>



        <script>
          function myFunction() {
            document.getElementById("myDropdown").classList.toggle("show");
          }

          window.onclick = function (event) {
            if (!event.target.matches('.dropbtn')) {
              var dropdowns = document.getElementsByClassName("dropdown-content");
              var i;
              for (i = 0; i < dropdowns.length; i++) {
                var openDropdown = dropdowns[i];
                if (openDropdown.classList.contains('show')) {
                  openDropdown.classList.remove('show');
                }
              }
            }
          }

          function close() {
            document.getElementById("del-drop").classList.toggle("rem1");
          }

          function confirmDel(self) {
            var id = self.getAttribute("data-id");

            document.getElementById("form-delete-user").id.value = id;

            document.getElementById("del-drop").classList.toggle("show1");

          }




          function updateClock() {
            const now = new Date();
            let hours = now.getHours();
            const minutes = now.getMinutes().toString().padStart(2, '0');
            const seconds = now.getSeconds().toString().padStart(2, '0');
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12 || 12; // convert 0 to 12
            document.getElementById('clock').textContent = `${hours}:${minutes}:${seconds} ${ampm}`;
          }
          setInterval(updateClock, 1000);
          updateClock();



          function openModal(el) {
            const orderId = el.getAttribute('data-id');
            document.getElementById('order_id').value = orderId;
            document.getElementById('chickenModal').style.display = 'flex';
          }

          function closeModal() {
            document.getElementById('chickenModal').style.display = 'none';
          }

          function validateChickenForm() {
            const qty = document.getElementById('quantity').value;
            const flavor = document.getElementById('flavor').value;

            if (qty < 3 || qty > 5) {
              alert("Please enter a quantity between 3 and 5.");
              return false; // prevent form submit
            }
            if (flavor === "") {
              alert("Please select a flavor.");
              return false;
            }
            return true; // allow form submit
          }

          function confirmPay(el) {
            const orderId = el.getAttribute('data-id');
            document.getElementById('pay_order_id').value = orderId;
            document.getElementById('paymentModal').style.display = 'flex';
          }

          function closePayModal() {
            document.getElementById('paymentModal').style.display = 'none';
          }



        </script>




        <script>
         // <CHANGE> Add modal state preservation before refresh
function preserveModalStates() {
  return {
    chickenModal: document.getElementById('chickenModal').style.display,
    paymentModal: document.getElementById('paymentModal').style.display,
    delDrop: document.getElementById('del-drop').classList.contains('show1')
  };
}

function restoreModalStates(states) {
  document.getElementById('chickenModal').style.display = states.chickenModal;
  document.getElementById('paymentModal').style.display = states.paymentModal;
  
  if (states.delDrop) {
    document.getElementById('del-drop').classList.add('show1');
  } else {
    document.getElementById('del-drop').classList.remove('show1');
  }
}

// Replace the content of the <main> element (or <body> if main missing) from fetched HTML
async function refreshOnce() {
  try {
    // <CHANGE> Preserve modal states before refresh
    const modalStates = preserveModalStates();
    
    const res = await fetch(window.location.href, { credentials: 'same-origin' });
    const html = await res.text();
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');
    const srcMain = doc.querySelector('main') || doc.body;
    const dstMain = document.querySelector('main') || document.body;
    if (!srcMain || !dstMain) return;

    const scroll = { x: window.scrollX, y: window.scrollY };

    const scripts = Array.from(srcMain.querySelectorAll('script'));

    scripts.forEach(s => s.parentNode && s.parentNode.removeChild(s));
    dstMain.innerHTML = srcMain.innerHTML;

    bindAjaxForms(dstMain);

    const temp = document.createElement('div');
    scripts.forEach(s => temp.appendChild(s.cloneNode(true)));
    runScriptsFromElement(temp);
    rebindGlobalHandlers();
    
    // <CHANGE> Restore modal states after refresh
    restoreModalStates(modalStates);
    
    window.scrollTo(scroll.x, scroll.y);
  } catch (e) { console.error('refreshOnce failed', e); }
}
        </script>


  </body>

  </html>

  <?php
} else {
  header("Location: login.php");
  exit();
}

