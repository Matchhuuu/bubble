<?php
session_start();

if (isset($_SESSION['ACC_ID']) && isset($_SESSION['EMAIL'])) { 

    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "bh";

    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        die("Database connection failed: " . $conn->connect_error);
    }

    $foods = [];
    $drinks = [];
    $sql = "SELECT ITEM_NAME, CURRENT_QUANTITY FROM unified_inventory";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            if (preg_match('/(MILK|STRAWBERRY|WATERMELON|BLUEBERRY|LYCHEE|MANGO|TEA)/i', $row['ITEM_NAME'])) {
                $drinks[] = $row;
            } else {
                $foods[] = $row;
            }
        }
        $stmt->close();
    } else {
        die("Error preparing statement: " . $conn->error);
    }

    $sales_data = [];
    $sql_sales = "SELECT DATE_OF_SALE, TOTAL_SALE FROM sale_records ORDER BY DATE_OF_SALE DESC";
    if ($stmt = $conn->prepare($sql_sales)) {
        $stmt->execute();
        $result_sales = $stmt->get_result();
        while ($row = $result_sales->fetch_assoc()) {
            $sales_data[] = $row;
        }
        $stmt->close();
    } else {
    }



$itemQuantities = [];
$itemQuery = "SELECT mi.name AS item_name, SUM(oi.quantity) AS total_quantity 
              FROM order_items oi 
              JOIN menu_items mi ON oi.menu_item_id = mi.id 
              GROUP BY mi.name";
$itemResult = $conn->query($itemQuery);

while ($row = $itemResult->fetch_assoc()) {
    $itemQuantities[$row['item_name']] = $row['total_quantity'];
}



    $conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href=/bubble/media/BUBBLE.jpg></link>
    <link rel="stylesheet" href="/bubble/interface/homepage.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <title>Inventory Summary</title>
    
    <link rel="stylesheet" href="/bubble/fonts/fonts.css">
    <style>
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
            justify-content: space-between;
            align-items: center;
        }
        .btn {
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

        /* Dropdown Styling */
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
        .dropdown {
            position: relative;
            width: 490px;
            display: flex;
            justify-content: right;
        }
        .dropdown-content {
            display: none;
            position: absolute;
            background-color: #f1f1f1;
            min-width: 110px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1;
            top: 80px;
            border-bottom-left-radius: 5px;
            border-bottom-right-radius: 5px;
        }
        .dropdown-content a {
            color: black;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
        }
        .dropdown-content a:hover {
            background-color: #ddd;
            border-bottom-left-radius: 5px;
            border-bottom-right-radius: 5px;
        }
        .show {
            display: block;
        }

        /* Main Container */
        .main {
            position: relative;
            width: 100%;
            height: auto;
            display: flex;
            justify-content: center;
            justify-content: space-evenly;
            
        }
        .scroll {
            position: relative;
            top: 60px;
            overflow: scroll;
            overflow-x: hidden;
            width: 100%;
            height:90vh;
            
        }
        h1{
            margin: 5px;
        }
        

    </style>
</head>
<body>
    <div class="navbar">
    <div style="position: relative; width: 20px; left: 30px; display: flex; align-items: center;"></div>
        <div class="buttons">
            <form action="/bubble/inventory/current_inventory.php">
                <button type="submit" class="btn"> Back </button>
            </form>
        </div>
    </div>
    <div class="navbar-right">
        <div class="dropdown">
            <button onclick="toggleDropdown()" class="dropbtn">Admin</button>
            <div id="myDropdown" class="dropdown-content">
                <a href="logout.php">Logout</a>
            </div>
        </div>
        <div style="position: relative; width: 20px; right: 30px; display: flex; align-items: center;"></div>
    </div>
    
    <div class="main" >
        <div class="scroll">
            <!--Bar Chart-->
            <div class="container" style="width: 90%; margin: auto; padding-top: 50px;">
                <h1>Inventory Overview</h1>
            
                <label for="filter">Filter by:</label>
                <select id="filter" onchange="applyFilter()">
                    <option value="all">All</option>
                    <option value="foods">Foods</option>
                    <option value="drinks">Drinks</option>
                </select>
           
                <canvas id="inventoryChart" width="1000" height="600"></canvas>
            </div>

            <!--Line Graph-->
            <div class="container1" style="width: 80%; margin: auto; padding-top: 50px;">
                <h1>POS Sales Overview</h1>
                <canvas id="pieChart" width="400" height="200" style="margin-top: 20px;"></canvas>
            </div>

            <!--Doughnut Chart-->
            <div class="chart-container" style="width: 40%; margin: auto; padding-top: 50px;">
                <h1 style="text-align:center">Order Items Overview</h1>
                <canvas id="itemQuantityChart"></canvas>
            </div>
        </div>   
    </div>

        

    <script>
        const allData = <?php echo json_encode(array_merge($foods, $drinks)); ?>;
        const foods = <?php echo json_encode($foods); ?>;
        const drinks = <?php echo json_encode($drinks); ?>;
        const salesData = <?php echo json_encode($sales_data); ?>;

        let inventoryChart, pieChart;


        const itemData = <?php echo json_encode($itemQuantities); ?>;

        const labels = Object.keys(itemData);
        const data = Object.values(itemData);

        const ctx = document.getElementById('itemQuantityChart').getContext('2d');
const itemQuantityChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [{
            label: 'Order Item Quantities',
            data: data,
            backgroundColor: [
                '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'
            ],
            hoverOffset: 4
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'top',
            },
            title: {
                display: true,
                text: 'Order Items and Their Quantities'
            }
        }
    }
});

        function createBarChart(data) {
            const ctx = document.getElementById('inventoryChart').getContext('2d');
            const labels = data.map(item => item.ITEM_NAME);
            const quantities = data.map(item => item.CURRENT_QUANTITY);

            if (inventoryChart) inventoryChart.destroy();

            inventoryChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Inventory',
                        data: quantities,
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.2)',
                            'rgba(54, 162, 235, 0.2)',
                            'rgba(255, 206, 86, 0.2)',
                            'rgba(75, 192, 192, 0.2)',
                            'rgba(153, 102, 255, 0.2)',
                            'rgba(20, 172, 123, 0.2)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(54, 162, 235, 1)',
                            'rgba(255, 206, 86, 1)',
                            'rgba(75, 192, 192, 1)',
                            'rgba(20, 172, 123, 1)',
                            'rgba(153, 102, 255, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    indexAxis: 'y',
                    scales: {
                        x: { beginAtZero: true },
                        y: { beginAtZero: true }
                    }
                }
            });
        }

        function createSalesDoughnutChart(data) {
            const ctx = document.getElementById('pieChart').getContext('2d');
            const labels = data.map(item => item.DATE_OF_SALE);
            const sales = data.map(item => item.TOTAL_SALE);

            if (pieChart) pieChart.destroy();

            pieChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Sales Overview',
                        data: sales,
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.2)',
                            'rgba(54, 162, 235, 0.2)',
                            'rgba(255, 206, 86, 0.2)',
                            'rgba(75, 192, 192, 0.2)',
                            'rgba(153, 102, 255, 0.2)',
                            'rgba(20, 172, 123, 0.2)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(54, 162, 235, 1)',
                            'rgba(255, 206, 86, 1)',
                            'rgba(75, 192, 192, 1)',
                            'rgba(20, 172, 123, 1)',
                            'rgba(153, 102, 255, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { position: 'top' } }
                }
            });
        }

        function applyFilter() {
            const filter = document.getElementById("filter").value;
            let data = allData;

            if (filter === "foods") data = foods;
            else if (filter === "drinks") data = drinks;

            createBarChart(data);
        }

        function toggleDropdown() {
            document.getElementById("myDropdown").classList.toggle("show");
        }

        createBarChart(allData);
        createSalesDoughnutChart(salesData);
    </script>

   
</body>
</html>

<?php
} else {
    header("Location: login.php");
    exit();
}
?>
