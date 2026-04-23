<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/session_handler.php';
include "db_conn.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id']) && isset($_POST['name']) && isset($_POST['price'])) {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $price = $_POST['price'];

    if (!empty($name) && !empty($price)) {
        $sql = "UPDATE unified_inventory 
                SET item_name='$name', cost_per_unit='$price' 
                WHERE item_id='$id'";
        mysqli_query($conn, $sql);
    }

    header("Location: inventory_list.php");
    exit;
}

if (isset($_POST['input'])) {
    $input = $_POST['input'];
    $sql = "SELECT * FROM unified_inventory
            WHERE item_id LIKE '{$input}%' OR item_name LIKE '{$input}%'";
} else {
    $sql = "SELECT * FROM unified_inventory";
}

$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) >= 0) {
?>
    <div class="container">
        <table class="table">
            <thead>
                <tr>
                    <th>Product ID</th>
                    <th>Product Name</th>
                    <th>Price</th>
                    <th><center>Option</center></th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = mysqli_fetch_assoc($result)) { ?>
                    <tr>
                        <td><?php echo $row["item_id"]; ?></td>
                        <td><?php echo $row["item_name"]; ?></td>
                        <td>Php <?php echo $row["cost_per_unit"]; ?></td>
                        <td>
                            <center>
                                <a class='btn-edit' 
                                   style='display:inline-block; transform: scale(-1, 1); width:30px; height:30px; font-size:20px;' 
                                   data-id='<?php echo $row['item_id']; ?>' 
                                   data-name='<?php echo htmlspecialchars($row['item_name'], ENT_QUOTES); ?>'
                                   data-price='<?php echo htmlspecialchars($row['cost_per_unit'], ENT_QUOTES); ?>'
                                   onclick='confirmDel(this)'>✎</a>

                                   <a class='btn-dgr' style='display:inline-block; width:30px; height: 30px; font-size: 20px;' data-id1='<?php echo $row['item_id']; ?>'  onclick='confirmRem(this)'>✖</a>
                                   
                            </center>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

    <script>
        function confirmDel(self) {
            var id = self.getAttribute("data-id");
            var name = self.getAttribute("data-name");
            var price = self.getAttribute("data-price");

            document.getElementById("edit_id").value = id;
            document.getElementById("edit_name").value = name;
            document.getElementById("edit_price").value = price;

            document.getElementById("del-drop").classList.toggle("show1");
        }

        function closeEdit() {
            document.getElementById("del-drop").classList.remove("show1");
        }

        function closeEdit1() {
            document.getElementById("del-drop1").classList.remove("show1");
        }

        function confirmRem(self){
        var id = self.getAttribute("data-id1");

        document.getElementById("form-remove-user").id.value = id;

        document.getElementById("del-drop1").classList.toggle("show1");

    }


    </script>

    <div class="a">
        <div class="dropdown-ver" id="del-drop">
            <div class="body">
                <p style="font-weight: 900; font-size: 20px; margin:auto">Edit Stock</p><br>
                <form method="POST" action="inventory_search.php" id="form-edit-item">
                    <div>
                        <input type="hidden" name="id" id="edit_id">
                        <label style="margin:auto;"><b>Stock Name</b></label><br>
                        <input type="text" style="width: 98%; border-radius: 5px;" name="name" id="edit_name">
                        
                        <label style="margin:auto;"><b>Stock Price</b></label><br>
                        <input type="text" style="width: 98%; border-radius: 5px;" name="price" id="edit_price">
                    </div>
                </form>
            </div>
            <div class="b" style="margin:auto;">
                <button type="button" style="display:inline-block; width: 100px;" class="btn-dgr" onclick="closeEdit()">Close</button>
                <button type="submit" style="display:inline-block; width: 100px;" form="form-edit-item" class="btn-edit">Confirm</button>
            </div>
        </div>
    </div>

    <div class="aa">
    <div class="dropdown-ver1" id="del-drop1">
            <div class="body">
                <Label style="margin:auto;">Are you sure you want to REMOVE this stock?</Label>
                <br>
                <form method="GET" action="remove_stock.php" id="form-remove-user">
                    <input type="hidden" name="id">
                </form>
                <br><br>
            </div>
            <div class="b" style="margin:auto;">
                <button type="button" style="display:inline-block; width: 100px; align-items:center" class="btn-edit" onclick="confirmRem(this)">Close</button>
                <button type="submit" style="display:inline-block; width: 100px; align-items:center" form="form-remove-user" class="btn-dgr">Delete</button>
            </div>
    </div>
</div>

    
<?php
} else {
    echo "<h5>No Record Found</h5>";
}
?>
