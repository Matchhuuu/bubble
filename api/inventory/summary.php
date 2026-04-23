<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/session_handler.php';

if (isset($_SESSION['ACC_ID']) && isset($_SESSION['EMAIL'])) {
    include "db_conn.php";


    $foods = [];
    $drinks = [];
    $sql = "SELECT ITEM_NAME, CURRENT_QUANTITY, is_liquid FROM unified_inventory";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            if ($row['is_liquid'] == 1) {
                $drinks[] = $row;
            } else {
                $foods[] = $row;
            }
        }
        $stmt->close();
    } else {
        die("Error preparing inventory statement: " . $conn->error);
    }

    $sales_data = [];
    $sql_sales = "SELECT DATE_OF_SALE, TOTAL_SALE FROM sale_records ORDER BY DATE_OF_SALE DESC limit 10";
    if ($stmt = $conn->prepare($sql_sales)) {
        $stmt->execute();
        $result_sales = $stmt->get_result();
        while ($row = $result_sales->fetch_assoc()) {
            $sales_data[] = $row;
        }
        $stmt->close();
    } else {
        die("Error preparing sales statement: " . $conn->error);
    }

    $itemQuantities = [];
    $today = date('Y-m-d');
    $itemQuery = "SELECT mi.name AS item_name, SUM(oi.quantity) AS total_quantity 
                  FROM customer_order_items oi 
                  JOIN menu_items mi ON oi.menu_item_id = mi.id 
                  WHERE DATE(oi.created_at) = '$today'
                  GROUP BY mi.name";
    $itemResult = $conn->query($itemQuery);
    while ($row = $itemResult->fetch_assoc()) {
        $itemQuantities[$row['item_name']] = $row['total_quantity'];
    }

    // Ratings
    function getRatingsData($conn) {
        $data = [];
        $result = $conn->query("SELECT stars, COUNT(*) AS count FROM ratings GROUP BY stars");
        while ($row = $result->fetch_assoc()) {
            $data[(int)$row['stars']] = (int)$row['count'];
        }
        return $data;
    }

    function getComments($conn) {
        $comments = [];
        $result = $conn->query("SELECT name, comment, created_at FROM feedback WHERE comment IS NOT NULL AND comment != '' ORDER BY created_at DESC LIMIT 20");
        while ($row = $result->fetch_assoc()) {
            $comments[] = $row;
        }
        return $comments;
    }

    $ratingsData = getRatingsData($conn);
    $comments = getComments($conn);
    $conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="/interface/homepage.css">
    <link rel="stylesheet" href="/fonts/fonts.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
    <style>
        body {
            font-family: Poppins;
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
        .graph-buttons {
            text-align: center;
            margin-top: 20px;
            padding-bottom: 20px;
            max-height: 10vh;
                        
        }
        .graph-buttons button {
            font-family: Poppins;
            background-color: #337609;
            color: white;
            border: none;
            padding: 10px 15px;
            margin: 5px;
            border-radius: 50px;
            font-size: 12px;
            cursor: pointer;

            transition: ease 0.5s;
        }
        .graph-buttons button:hover {
            font-size: 15px;
            background-color: #326810;

            transition: ease 0.5s;
        }
        .graph-section {
            
            max-width: 75%;
            margin: auto;
            text-align: center;
        }
        #commentsList {
            list-style-type: disc;
            padding-left: 40px;
            text-align: left;
        }

.comments-wrapper {
    display: flex;
    flex-wrap: wrap;
    
    justify-content: center;     
    gap: 20px;
    
    overflow-y: auto;
    padding: 10px;
}

.comment-card {
    background-color: white;
    border: 2px solid #337609;
    border-radius: 10px;
    padding: 15px;
    width: calc(33.33% - 20px);
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    font-family: Poppins, sans-serif;
    text-align: left;           
}

.comment-name {
    font-weight: bold;
    font-size: 30px;
    margin-bottom: -5px;
}

.comment-date {
    font-size: 0.9em;
    color: rgba(0, 0, 0, 0.5);
    margin-bottom: 10px;
}

.comment-text {
    font-family: Poppins, sans-serif;
}

@media (max-width: 768px) {
    .comment-card {
    min-width: 90%;  /* full width stack on small screens */
    }
}

    </style>
</head>
<body>

<div class="navbar">
    <div style="position: relative; width: 20px; left: 30px; display: flex; align-items: center;"></div>
        <div class="buttons">
            <form action="/interface/admin_homepage.php">
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

  
<!-- Control Buttons -->
<div class="graph-buttons">
    <br><br><br>
    <button onclick="showGraph('inventorySection')">Inventory</button>
    <button onclick="showGraph('salesSection')">Sales Graph</button>
    <button onclick="showGraph('orderItemsSection')">Ordered Items</button>
    <button onclick="showGraph('starRatingSection')">Star Ratings</button>
    <button onclick="showGraph('commentSection')">Comments</button>
</div>

<!-- Inventory Graph -->
<div class="graph-section" id="inventorySection">
    <h2>Inventory Overview</h2>
    <label for="filter">Filter:</label>
    <select id="filter" onchange="applyFilter()">
        <option value="all">All</option>
        <option value="foods">Foods</option>
        <option value="drinks">Drinks</option>
    </select>
    <canvas id="inventoryChart"></canvas>
</div>

<!-- Sales Graph -->
<div class="graph-section" id="salesSection" style="display: none;">
    <h2>POS Sales Overview</h2>
    <canvas id="salesChart"></canvas>
</div>

<!-- Order Items -->
<div class="graph-section" id="orderItemsSection" style="display: none;">
    <h2>Order Items Summary (Today)</h2>
    <canvas id="orderItemsChart"></canvas>
</div>

<!-- Star Rating Graph -->
<div class="graph-section" id="starRatingSection" style="display: none;">
    <h2>Star Rating Distribution</h2>
    <canvas id="ratingsChart"></canvas>
</div>

<!-- Comments -->
<div class="graph-section" id="commentSection" style="display: none;">
    <h2>Customer Comments (Recent 20)</h2>
    <div id="commentsContainer" class="comments-wrapper"></div>
</div>

<script>
    const allData = <?= json_encode(array_merge($foods, $drinks)) ?>;
    const foods = <?= json_encode($foods) ?>;
    const drinks = <?= json_encode($drinks) ?>;
    const salesData = <?= json_encode($sales_data) ?>;
    const itemData = <?= json_encode($itemQuantities) ?>;
    const ratings = <?= json_encode($ratingsData) ?>;
    const comments = <?= json_encode($comments) ?>;
    
    Chart.register(ChartDataLabels);

    // Show graph sections
    function showGraph(id) {
        document.querySelectorAll('.graph-section').forEach(div => div.style.display = 'none');
        document.getElementById(id).style.display = 'block';
    }

    let inventoryChart;
    function createInventoryChart(data) {
        const ctx = document.getElementById('inventoryChart').getContext('2d');
        const labels = data.map(item => item.ITEM_NAME);
        const quantities = data.map(item => item.CURRENT_QUANTITY);
        
        const colors = data.map(item => item.is_liquid == 1 ? '#FFD700' : '#22C55E');
        
        if (inventoryChart) inventoryChart.destroy();

        inventoryChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'Quantity',
                    data: quantities,
                    backgroundColor: colors,
                    hoverOffset: 4
                }]
            },
            options: {
                indexAxis: 'y',
                scales: { x: { beginAtZero: true } }
            }
        });
    }
    function applyFilter() {
        const filter = document.getElementById("filter").value;
        if (filter === "foods") createInventoryChart(foods);
        else if (filter === "drinks") createInventoryChart(drinks);
        else createInventoryChart(allData);
    }

    // Sales Chart with labels on each point
    new Chart(document.getElementById('salesChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: salesData.map(r => r.DATE_OF_SALE),
            datasets: [{
                label: 'Total Sales',
                data: salesData.map(r => parseFloat(r.TOTAL_SALE)),
                borderColor: 'rgba(54, 162, 235, 1)',
                backgroundColor: 'rgba(54, 162, 235, 0.1)',
                tension: 0.3,
                fill: false,
                pointRadius: 6,
                pointBackgroundColor: 'rgba(54, 162, 235, 1)'
            }]
        },
        options: {
            responsive: true,
            layout: {
                padding: {
                    top: 50
                }
            },
            plugins: {
                legend: {
                    display: true
                },
                tooltip: {
                    enabled: false
                },
                datalabels: {
                    display: true,
                    anchor: 'end',
                    align: 'top',
                    offset: 10,
                    font: { 
                        weight: 'bold', 
                        size: 12
                    },
                    color: '#333',
                    formatter: function(value) {
                        return '₱' + parseFloat(value).toFixed(2);
                    }
                }
            },
            scales: { 
                y: { 
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '₱' + value.toFixed(0);
                        }
                    }
                } 
            }
        }
    });

    new Chart(document.getElementById('orderItemsChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: Object.keys(itemData),
            datasets: [{
                label: 'Total Ordered Today',
                data: Object.values(itemData),
                backgroundColor: 'rgba(255, 159, 64, 0.6)'
            }]
        },
        options: {
            responsive: true,
            scales: { y: { beginAtZero: true } }
        }
    });

    // Star Ratings Chart
    new Chart(document.getElementById('ratingsChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: ["1 Star", "2 Stars", "3 Stars", "4 Stars", "5 Stars"],
            datasets: [{
                label: 'Count',
                data: [1,2,3,4,5].map(r => ratings[r] || 0),
                backgroundColor: 'rgba(255, 205, 86, 0.8)'
            }]
        },
        options: {
            responsive: true,
            scales: { y: { beginAtZero: true } }
        }
    });

    // Comments
    const commentsContainer = document.getElementById('commentsContainer');
    comments.forEach(comment => {
       
        const card = document.createElement('div');
        card.className = 'comment-card';

        card.innerHTML = `
            <div class="comment-name">${comment.name}</div>
            <div class="comment-date">${comment.created_at}</div>
            <div class="comment-text">${comment.comment}</div>
        `;

        commentsContainer.appendChild(card);
    });


    // Initialize
    createInventoryChart(allData);
    showGraph('inventorySection');
    
</script>
<script>
    function toggleDropdown() {
    document.getElementById("myDropdown").classList.toggle("show");
}

window.onclick = function(event) {
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
</script>


</body>
</html>
<?php } else { header("Location: login.php"); exit(); } ?>
