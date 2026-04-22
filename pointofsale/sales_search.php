<?php
include("db_conn.php");

if(isset($_POST['input'])){

    $input = $_POST['input'];

    $sql = "SELECT * FROM sale_records s
            JOIN accounts a ON a.acc_id = s.last_transact
            WHERE s.DATE_OF_SALE LIKE '{$input}%' OR s.LAST_TRANSACT LIKE '{$input}%' OR a.FNAME LIKE '{$input}%' OR a.LNAME LIKE '{$input}%'
            ORDER BY DATE_OF_SALE DESC";

     $result = mysqli_query($conn, $sql);
     
     if (mysqli_num_rows($result) >= 0){?>
        <div class="container">
        <table class="table">
            <thead>
                <tr>
                    <th>Date of Sale</th>
                    <th>Total Sale</th>
                    <th>Account who Ended Sale</th>
                    <th>First Name</th>
                    <th>Last Name</th>

                </tr>
            </thead>

            <tbody>
                <?php
            
                while($row = mysqli_fetch_assoc($result)){
                    $itemname = $row["DATE_OF_SALE"];
                    $size = $row["TOTAL_SALE"];
                    $last = $row["LAST_TRANSACT"];
                    $lname = $row["LNAME"];
                    $fname = $row["FNAME"];
                    
    
                ?>

                    <tr>
                        <td><?php echo $itemname;?></td>
                        <td><?php echo $size;?></td>
                        <td><?php echo $last;?></td>
                        <td><?php echo $fname;?></td>
                        <td><?php echo $lname;?></td>

                    </tr>

                    <?php
                }
            ?>
            </tbody>
        </table>
        </div>

        <script>
            function close() {
                document.getElementById("del-drop").classList.toggle("rem1");
            }

            function confirmDel(self){
                var id = self.getAttribute("data-id");
        
                document.getElementById("form-delete-user").id.value = id;

                document.getElementById("del-drop").classList.toggle("show1");

            }
        </script>

        <div class="a">
            <div class="dropdown-ver" id="del-drop">
                    <div class="body">
                        <p>Edits Item</p>
                        <form method="GET" action="food_edit.php" id="form-delete-user">
                            <div>
                                <p>Are you sure you want to EDIT the selected item?</p>
                                <input type="hidden" style="width: 50%; border-radius: 5px;" name="id">
                            </div>                                                                                    
                        </form>
                    </div>
                    <div class="b">
                        <button type="button" style="display:inline-block; width: 100px; align-items:center" class="btn-edit" onclick="confirmDel(this)">Close</button>
                        <button type="submit" style="display:inline-block; width: 100px; align-items:center" form="form-delete-user" class="btn-edit">Edit</button>

                    </div>
            </div>
        </div>
            
        <?php

     }

     else {
        echo "<h5>No Record Found</h5>";
     }
}

else{

    $sql = "SELECT * FROM sale_records s
            JOIN accounts a ON a.acc_id = s.last_transact
            ORDER BY DATE_OF_SALE DESC";

     $result = mysqli_query($conn, $sql);
     
     if (mysqli_num_rows($result) >= 0){?>
        
        

        <div class="container">
        <table class="table">
            <thead>
                <tr>
                    <th>Date of Sale</th>
                    <th>Total Sale</th>
                    <th>Account who Ended Sale</th>
                    <th>First Name</th>
                    <th>Last Name</th>

                </tr>
            </thead>

            <tbody>
                <?php
            
                while($row = mysqli_fetch_assoc($result)){
                    $itemname = $row["DATE_OF_SALE"];
                    $size = $row["TOTAL_SALE"];
                    $last = $row["LAST_TRANSACT"];
                    $lname = $row["LNAME"];
                    $fname = $row["FNAME"];
                    
    
                ?>

                    <tr>
                        <td><?php echo $itemname;?></td>
                        <td> Php <?php echo $size;?></td>
                        <td><?php echo $last;?></td>
                        <td><?php echo $fname;?></td>
                        <td><?php echo $lname;?></td>

                    </tr>

                    <?php
                }
            ?>
            </tbody>
        </table>
        </div>

        <script>
            function close() {
                document.getElementById("del-drop").classList.toggle("rem1");
            }

            function confirmDel(self){
                var id = self.getAttribute("data-id");
        
                document.getElementById("form-delete-user").id.value = id;

                document.getElementById("del-drop").classList.toggle("show1");

            }
        </script>

        <div class="a">
            <div class="dropdown-ver" id="del-drop">
                    <div class="body">
                        <p>Edit Item</p>
                        <form method="GET" action="food_edit.php" id="form-delete-user">
                            <div>
                                <p>Are you sure you want to EDIT the selected item?  </p>
                                <input type="hidden" style="width: 50%; border-radius: 5px;" name="id">
                            </div>                                                                                    
                        </form>
                    </div>
                    <div class="b">
                        <button type="button" style="display:inline-block; width: 100px; align-items:center" class="btn-edit" onclick="confirmDel(this)">Close</button>
                        <button type="submit" style="display:inline-block; width: 100px; align-items:center" form="form-delete-user" class="btn-edit">Edit</button>

                    </div>
            </div>
        </div>

        
        <?php

     }

}


?>