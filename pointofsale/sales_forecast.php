<?php
session_start();

if (isset($_SESSION['ACC_ID']) && isset($_SESSION['EMAIL'])) {
    $conn = new mysqli("localhost", "root", "", "bh");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $sales_data = [];
    $sql = "SELECT DATE_OF_SALE, SUM(TOTAL_SALE) AS TOTAL_SALE 
            FROM sale_records 
            GROUP BY DATE_OF_SALE 
            ORDER BY DATE_OF_SALE DESC 
            LIMIT 20";
    $result = $conn->query($sql);

    while ($row = $result->fetch_assoc()) {
        $sales_data[] = [
            'date' => $row['DATE_OF_SALE'],
            'sale' => (float) $row['TOTAL_SALE']
        ];
    }

    $sales_data = array_reverse($sales_data);

    $conn->close();

    $dates = array_column($sales_data, 'date');
    $actual_sales = array_column($sales_data, 'sale');
    ?>
    <html>

    <head>
        <title>Sales Forecast</title>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
        <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
        <link rel="stylesheet" href="/bubble/fonts/fonts.css">
        <style>
            body {
                margin-top: 5%;
                display: flex;
                gap: 20px;
                padding: 20px;
                background: #f5f5f5;
            }

            #left {
                flex: 3;
            }

            #right {
                flex: 1;
                background: #fff;
                padding: 20px;
                border-radius: 10px;
                box-shadow: 0 0 15px rgba(0, 0, 0, 0.2);
                overflow-y: auto;
                max-height: 90vh;
            }

            canvas {
                background: #fff;
                padding: 10px;
                border-radius: 10px;
                box-shadow: 0 0 15px rgba(0, 0, 0, 0.2);
            }

            input,
            select {
                width: 100%;
                padding: 10px;
                margin-top: 10px;
                box-sizing: border-box;
            }

            .navbar {
                background-color: #7e5832;
                width: 100%;
                height: 80px;
                position: absolute;
                top: 0;
                left: 0;
                z-index: 10;
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
                justify-content: space-between;
                align-items: center;
            }

            .btn {
                font-family: Poppins;
                font-weight: bold;
                color: #f0f0f0;
                box-shadow: 3px 4px 11px 1px rgba(0, 0, 0, 0.28);
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
                box-shadow: 0px 8px 16px 0px rgba(0, 0, 0, 0.2);
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

            .summary-boxes {
                display: flex;
                justify-content: space-around;
                margin-bottom: 20px;
            }

            .summary-boxes .box {
                flex: 1;
                margin: 0 10px;
                background: #fff;
                border-radius: 12px;
                padding: 20px;
                text-align: center;
                box-shadow: 0 0 12px rgba(0, 0, 0, 0.15);
            }

            .summary-boxes .box h2 {
                font-size: 24px;
                margin: 0;
                font-weight: bold;
            }

            .summary-boxes .box p {
                margin: 5px 0 0;
                font-size: 14px;
                color: #555;
            }

            .summary-boxes .daily h2 {
                color: green;
            }

            .summary-boxes .weekly h2 {
                color: blue;
            }

            .summary-boxes .monthly h2 {
                color: orange;
            }


            .date-container {
                display: flex;
                gap: 10px;
            }

            .date-group {
                width: 50%;
            }

            .date-group1 {
                width: auto;
            }

            .container1 {
                min-height: 80vh;
                /* full screen height */
                display: flex;
                flex-direction: column;
            }

            .chart-buttons {
                display: flex;
                justify-content: left;
                gap: 10px;
                margin-top: 20px;
                margin-bottom: 10px;
            }

            .chart-toggle {
                padding: 8px 14px;
                background-color: #4CAF50;
                color: white;
                border: none;
                border-radius: 20px;
                font-family: Poppins;
                font-weight: 600;
                cursor: pointer;
                box-shadow: 2px 3px 8px rgba(0, 0, 0, 0.2);
                transition: background-color 0.3s;
            }

            .chart-toggle:hover {
                background-color: #3e8e41;
            }

            .chart-toggle.active {
                background-color: #2e7030;
            }
        </style>
    </head>

    <body>
        <div class="navbar">
            <div style="position: relative; width: 20px; left: 30px; display: flex; align-items: center;"></div>
            <div class="buttons">
                <form action="/bubble/interface/admin_homepage.php">
                    <button type="submit" class="btn"> Back </button>
                </form>
            </div>
        </div>
        <div class="navbar-right">
            <div class="dropdown">
                <button onclick="toggleDropdown()" class="dropbtn">Admin</button>
                <div id="myDropdown" class="dropdown-content">
                    <a href="/bubble/interface/logout.php">Logout</a>
                </div>
            </div>
            <div style="position: relative; width: 20px; right: 30px; display: flex; align-items: center;"></div>
        </div>

        <div id="left">
            <h2>Sales Data</h2>

            <div id="salesSummary" class="summary-boxes">
                <div class="box daily">
                    <h2 id="dailyTotal">₱0</h2>
                    <p>AVERAGE DAILY SALES</p>
                </div>
                <div class="box weekly">
                    <h2 id="weeklyTotal">₱0</h2>
                    <p>AVERAGE WEEKLY SALES</p>
                </div>
                <div class="box monthly">
                    <h2 id="monthlyTotal">₱0</h2>
                    <p>AVERAGE MONTHLY SALES</p>
                </div>
            </div>

            <canvas id="forecastChart" height="100"></canvas>
        </div>

        <div id="right" class="container1">
            <div>
                <h4 style="font-family: Poppins;">Filter by View</h4>
                <label for="filterType">Select View:</label>
                <select id="filterType" onchange="applyFilter()" style="font-family: Poppins;">
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly</option>
                    <option value="monthly">Monthly</option>
                </select>
            </div>
            
            <div class="chart-buttons" style="margin-top: 10px;">
                <button id="btnForecast" class="chart-toggle active">Forecast Daily</button>
                
                <button id="btnForecastWeekly" class="chart-toggle">Forecast Weekly</button>
                
                <button id="btnForecastMonthly" class="chart-toggle">Forecast Monthly</button>
            </div>

            <div class="date-container" id="dailyInputs">
                <div class="date-group">
                    <label for="startDate">Select Start Date</label>
                    <input type="text" id="startDate" />
                </div>

                <div class="date-group">
                    <label for="endDate">Select End Date</label>
                    <input type="text" id="endDate" />
                </div>

                <div class="date-group1">
                    <label for="forecastDays">Days to Forecast</label>
                    <input style="font-family: Poppins;" type="number" id="forecastDays" min="1" max="14"
                        placeholder="e.g. 3">
                </div>
            </div>

            <div style="margin-top: 10px;">
                <label for="forecastMethod" style="font-family: Poppins;">Forecast Method</label>
                <select id="forecastMethod" style="font-family: Poppins; width: 100%; padding: 10px;">
                    <option value="sma">Simple Moving Average (SMA)</option>
                    <option value="exponential">Exponential Smoothing (ES)</option>
                </select>
            </div>

            <!-- Updated button to call appropriate forecast function based on active tab -->
            <button
                style="width: 100%;padding: 10px;margin-top: 10px;font-family: Poppins;background-color: #4CAF50;color: white;border: none;"
                onclick="handleForecastClick()">Generate Forecast</button>
            <button
                style="width: 100%;padding: 10px;margin-top: 10px;font-family: Poppins;background-color: #4CAF50;color: white;border: none;"
                onclick="showAllSales()">Show All Sales</button>
        </div>

        <script>

            const originalDates = <?= json_encode($dates) ?>;
            const originalSales = <?= json_encode($actual_sales) ?>;
            const ctx = document.getElementById('forecastChart').getContext('2d');
            let chart;


            const forecastChartCanvas = document.getElementById('forecastChart');
            const btnForecast = document.getElementById('btnForecast');
            const btnForecastWeekly = document.getElementById('btnForecastWeekly');
            const btnForecastMonthly = document.getElementById('btnForecastMonthly');
            let customInputsContainer;
            let currentForecastType = 'daily';


            function setActiveButton(activeBtn) {
                [btnForecast, btnForecastWeekly, btnForecastMonthly].forEach(btn => btn.classList.remove('active'));
                activeBtn.classList.add('active');
            }


            btnForecast.addEventListener('click', () => {
                setActiveButton(btnForecast);
                currentForecastType = 'daily';
                showInputSet("daily");
                forecastChartCanvas.style.display = 'block';
            });

            btnForecastWeekly.addEventListener('click', () => {
                setActiveButton(btnForecastWeekly);
                currentForecastType = 'weekly';
                showInputSet("weekly");
                forecastChartCanvas.style.display = 'block';
            });

            btnForecastMonthly.addEventListener('click', () => {
                setActiveButton(btnForecastMonthly);
                currentForecastType = 'monthly';
                showInputSet("monthly");
                forecastChartCanvas.style.display = 'block';
            });

            function showInputSet(type) {
                if (customInputsContainer) customInputsContainer.remove();

                if (type === "daily") {
                    dailyInputs.style.display = "flex";
                } else {
                    dailyInputs.style.display = "none";
                    customInputsContainer = document.createElement("div");
                    customInputsContainer.classList.add("date-container");
                    customInputsContainer.style.marginTop = "10px";

                    if (type === "weekly") {
                        customInputsContainer.innerHTML = `
                            <div class="date-group">
                                <label>Starting Week</label>
                                <input type="week" id="startWeek">
                            </div>
                            <div class="date-group">
                                <label>Ending Week</label>
                                <input type="week" id="endWeek">
                            </div>
                            <div class="date-group1">
                                <label>Weeks to Forecast</label>
                                <input type="number" id="forecastWeeks" min="1" max="8" placeholder="e.g. 2">
                            </div>
                        `;
                    } else if (type === "monthly") {
                        customInputsContainer.innerHTML = `
                            <div class="date-group">
                                <label>Starting Month</label>
                                <input type="month" id="startMonth">
                            </div>
                            <div class="date-group">
                                <label>Ending Month</label>
                                <input type="month" id="endMonth">
                            </div>
                            <div class="date-group1">
                                <label>Months to Forecast</label>
                                <input type="number" id="forecastMonths" min="1" max="6" placeholder="e.g. 2">
                            </div>
                        `;
                    }
                    const chartButtonsDiv = document.querySelector('.chart-buttons');
                    if (chartButtonsDiv) {
                        chartButtonsDiv.parentNode.insertBefore(customInputsContainer, chartButtonsDiv.nextSibling);
                    } else {
                        document.getElementById("right").appendChild(customInputsContainer);
                    }
                }
            }


            function generateWeeklyForecast() {
                const startWeek = document.getElementById("startWeek").value;
                const endWeek = document.getElementById("endWeek").value;
                const forecastWeeks = parseInt(document.getElementById("forecastWeeks").value);
                const method = document.getElementById("forecastMethod").value;

                if (!startWeek || !endWeek || startWeek > endWeek) {
                    alert("Please select valid start and end weeks.");
                    return;
                }
                if (!forecastWeeks || forecastWeeks < 1 || forecastWeeks > 8) {
                    alert("Please enter a forecast duration between 1 and 8 weeks.");
                    return;
                }

                const weeklyData = {};
                function getISOWeekKey(date) {
                    const tmp = new Date(date);
                    tmp.setDate(tmp.getDate() + 3 - ((tmp.getDay() + 6) % 7));
                    const week1 = new Date(tmp.getFullYear(), 0, 4);
                    const weekNo = Math.round(((tmp - week1) / 86400000 - 3 + ((week1.getDay() + 6) % 7)) / 7) + 1;
                    return `${tmp.getFullYear()}-W${String(weekNo).padStart(2, "0")}`;
                }

                originalDates.forEach((d, i) => {
                    const key = getISOWeekKey(new Date(d));
                    weeklyData[key] = (weeklyData[key] || 0) + originalSales[i];
                });

                const weekKeys = Object.keys(weeklyData).sort();
                const startIndex = weekKeys.indexOf(startWeek);
                const endIndex = weekKeys.indexOf(endWeek);

                if (startIndex === -1 || endIndex === -1 || startIndex > endIndex) {
                    alert("Selected week range not found in data.");
                    return;
                }

                const weeks = weekKeys.slice(startIndex, endIndex + 1);
                const sales = weeks.map(w => weeklyData[w]);
                plotForecast(weeks, sales, forecastWeeks, "Weekly", method);
            }

            function generateMonthlyForecast() {
                const startMonth = document.getElementById("startMonth").value;
                const endMonth = document.getElementById("endMonth").value;
                const forecastMonths = parseInt(document.getElementById("forecastMonths").value);
                const method = document.getElementById("forecastMethod").value;

                if (!startMonth || !endMonth || startMonth > endMonth) {
                    alert("Please select valid start and end months.");
                    return;
                }
                if (!forecastMonths || forecastMonths < 1 || forecastMonths > 6) {
                    alert("Please enter a forecast duration between 1 and 6 months.");
                    return;
                }

                const monthlyData = {};
                originalDates.forEach((d, i) => {
                    const key = d.slice(0, 7);
                    monthlyData[key] = (monthlyData[key] || 0) + originalSales[i];
                });

                const monthKeys = Object.keys(monthlyData).sort();
                const startIndex = monthKeys.indexOf(startMonth);
                const endIndex = monthKeys.indexOf(endMonth);

                if (startIndex === -1 || endIndex === -1 || startIndex > endIndex) {
                    alert("Selected month range not found in data.");
                    return;
                }

                const months = monthKeys.slice(startIndex, endIndex + 1);
                const sales = months.map(m => monthlyData[m]);
                plotForecast(months, sales, forecastMonths, "Monthly", method);
            }


            function plotForecast(labels, values, steps, type, method) {
                let forecasted = [];
                let combined = [...values];

                if (method === "sma") {
                    const windowSize = 3;
                    for (let i = 0; i < steps; i++) {
                        const avg = combined.slice(-windowSize).reduce((a, b) => a + b, 0) / windowSize;
                        forecasted.push(avg);
                        combined.push(avg);
                    }
                } else {
                    const alpha = 0.5, beta = 0.3;
                    let level = values[0], trend = values[1] - values[0];
                    for (let i = 1; i < values.length; i++) {
                        let prevLevel = level;
                        level = alpha * values[i] + (1 - alpha) * (level + trend);
                        trend = beta * (level - prevLevel) + (1 - beta) * trend;
                    }
                    for (let i = 0; i < steps; i++) forecasted.push(level + (i + 1) * trend);
                }

                const forecastLabels = Array.from({ length: steps }, (_, i) => `${type} +${i + 1}`);
                const finalLabels = [...labels, ...forecastLabels];
                const finalValues = [...values, ...forecasted];

                const pointBackgroundColors = [
                    ...values.map(() => '#4CAF50'), // Green for actual data
                    ...forecasted.map(() => '#2196F3') // Blue for forecasted data
                ];

                if (chart) chart.destroy();
                chart = new Chart(ctx, {
                    type: "line",
                    data: {
                        labels: finalLabels,
                        datasets: [{
                            label: `${type} ${method === 'sma' ? 'SMA' : 'Exponential'} Forecast`,
                            data: finalValues,
                            borderColor: method === 'sma' ? 'green' : 'blue',
                            backgroundColor: 'rgba(76, 175, 80, 0.1)',
                            borderWidth: 2,
                            tension: 0.3,
                            pointRadius: 5,
                            pointBackgroundColor: pointBackgroundColors,
                            pointBorderColor: pointBackgroundColors,
                            pointBorderWidth: 2,
                            segment: { borderDash: ctx => ctx.p0DataIndex < values.length - 1 ? undefined : [5, 5] }
                        }]
                    },
                    options: {
                        plugins: {
                            title: { display: true, text: `${type} Forecast (${steps} ${type === "Weekly" ? "Weeks" : "Months"})` },
                            datalabels: {
                                anchor: 'end',
                                align: 'top',
                                color: '#000',
                                font: { weight: 'bold', size: 12 },
                                formatter: (v, context) => {
                                    const isActual = context.dataIndex < values.length;
                                    return `₱${v.toFixed(2)}`;
                                },
                                backgroundColor: (context) => {
                                    const isActual = context.dataIndex < values.length;
                                    return isActual ? '#4CAF50' : '#2196F3';
                                },
                                color: '#fff',
                                borderRadius: 4,
                                padding: 4
                            }
                        },
                        scales: { y: { beginAtZero: true } },
                        responsive: true
                    },
                    plugins: [ChartDataLabels]
                });
            }


            function generateSMAForecast() {
                const start = document.getElementById("startDate").value;
                const end = document.getElementById("endDate").value;
                const forecastDays = parseInt(document.getElementById("forecastDays").value);
                const method = document.getElementById("forecastMethod").value;

                if (!start || !end || start === end) {
                    alert("Please select a valid range with different start and end dates.");
                    return;
                }
                if (!forecastDays || forecastDays < 1 || forecastDays > 14) {
                    alert("Please enter a forecast duration between 1 and 14 days.");
                    return;
                }

                const startIndex = originalDates.indexOf(start);
                const endIndex = originalDates.indexOf(end);
                if (startIndex === -1 || endIndex === -1 || startIndex > endIndex) {
                    alert("Invalid date range selected.");
                    return;
                }

                const dateRange = originalDates.slice(startIndex, endIndex + 1);
                const salesRange = originalSales.slice(startIndex, endIndex + 1);

                if (salesRange.length < 3 && method === 'sma') {
                    alert("At least 3 data points are needed to compute the moving average.");
                    return;
                }

                let forecastedSales = [];
                let combined = [...salesRange];

                if (method === 'sma') {
                    const windowSize = 3;
                    for (let i = 0; i < forecastDays; i++) {
                        const lastWindow = combined.slice(-windowSize);
                        const sma = parseFloat((lastWindow.reduce((a, b) => a + b, 0) / windowSize).toFixed(2));
                        forecastedSales.push(sma);
                        combined.push(sma);
                    }
                } else if (method === 'exponential') {
                    const alpha = 0.5;
                    const beta = 0.3;

                    let level = salesRange[0];
                    let trend = salesRange[1] - salesRange[0];

                    for (let i = 1; i < salesRange.length; i++) {
                        let prevLevel = level;
                        level = alpha * salesRange[i] + (1 - alpha) * (level + trend);
                        trend = beta * (level - prevLevel) + (1 - beta) * trend;
                    }

                    for (let i = 0; i < forecastDays; i++) {
                        forecastedSales.push(parseFloat((level + (i + 1) * trend).toFixed(2)));
                    }
                }

                const forecastedLabels = Array.from({ length: forecastDays }, (_, i) => `Day +${i + 1}`);
                const combinedLabels = [...dateRange, ...forecastedLabels];
                const combinedSales = [...salesRange, ...forecastedSales];

                if (chart) chart.destroy();
                chart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: combinedLabels,
                        datasets: [{
                            label: `Sales + ${forecastDays}-Day ${method === 'sma' ? 'SMA' : 'Exponential'} Forecast`,
                            data: combinedSales,
                            borderColor: method === 'sma' ? 'green' : 'blue',
                            borderWidth: 2,
                            pointRadius: 4,
                            tension: 0.4,
                            spanGaps: true,
                            segment: {
                                borderDash: ctx => ctx.p0DataIndex < salesRange.length - 1 ? undefined : [5, 5]
                            }
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            title: {
                                display: true,
                                text: `${method === 'sma' ? 'Recursive SMA' : 'Exponential Smoothing'} Forecast (${forecastDays} Days) from ${end}`
                            },
                            datalabels: {
                                anchor: 'end',
                                align: 'top',
                                color: '#000',
                                font: { weight: 'bold', size: 12 },
                                formatter: (value) => `${value}`
                            }
                        },
                        scales: { y: { beginAtZero: true } }
                    },
                    plugins: [ChartDataLabels]
                });
            }


            function handleForecastClick() {
                if (currentForecastType === 'daily') {
                    generateSMAForecast();
                } else if (currentForecastType === 'weekly') {
                    generateWeeklyForecast();
                } else if (currentForecastType === 'monthly') {
                    generateMonthlyForecast();
                }
            }


            function toggleDropdown() {
                document.getElementById("myDropdown").classList.toggle("show");
            }

            function applyFilter() {
                const filterType = document.getElementById("filterType").value;
                const parsedData = originalDates.map((d, i) => ({
                    date: new Date(d),
                    sale: parseFloat(originalSales[i]) || 0
                }));
                parsedData.sort((a, b) => a.date - b.date);

                let groupedLabels = [];
                let groupedSales = [];

                if (filterType === "daily") {
                    groupedLabels = parsedData.map(item => item.date.toISOString().split("T")[0]);
                    groupedSales = parsedData.map(item => item.sale);
                } else if (filterType === "weekly") {
                    const weeklyData = {};
                    function getISOWeekKey(date) {
                        const tmp = new Date(date.getTime());
                        tmp.setHours(0, 0, 0, 0);
                        tmp.setDate(tmp.getDate() + 3 - ((tmp.getDay() + 6) % 7));
                        const week1 = new Date(tmp.getFullYear(), 0, 4);
                        const weekNo = Math.round(((tmp.getTime() - week1.getTime()) / 86400000 - 3 + ((week1.getDay() + 6) % 7)) / 7) + 1;
                        return `${tmp.getFullYear()}-W${String(weekNo).padStart(2, "0")}`;
                    }

                    parsedData.forEach(item => {
                        const key = getISOWeekKey(item.date);
                        weeklyData[key] = (weeklyData[key] || 0) + item.sale;
                    });
                    groupedLabels = Object.keys(weeklyData).sort();
                    groupedSales = groupedLabels.map(label => weeklyData[label]);
                } else if (filterType === "monthly") {
                    const monthlyData = {};
                    parsedData.forEach(item => {
                        const key = `${item.date.getFullYear()}-${String(item.date.getMonth() + 1).padStart(2, "0")}`;
                        monthlyData[key] = (monthlyData[key] || 0) + item.sale;
                    });
                    groupedLabels = Object.keys(monthlyData).sort();
                    groupedSales = groupedLabels.map(label => monthlyData[label]);
                }

                if (chart) chart.destroy();
                chart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: groupedLabels,
                        datasets: [{
                            label: `Sales (${filterType})`,
                            data: groupedSales,
                            borderColor: 'orange',
                            backgroundColor: 'rgba(255,165,0,0.2)',
                            borderWidth: 2,
                            pointRadius: 4,
                            tension: 0.2,
                            pointBackgroundColor: 'orange'
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            title: {
                                display: true,
                                text: `Sales Data (${filterType.charAt(0).toUpperCase() + filterType.slice(1)})`
                            },
                            datalabels: {
                                anchor: 'end',
                                align: 'top',
                                color: '#000',
                                font: { weight: 'bold', size: 12 },
                                formatter: (value) => value.toLocaleString()
                            }
                        }
                    },
                    plugins: [ChartDataLabels]
                });
            }

            function showAllSales() {
                if (chart) chart.destroy();
                chart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: originalDates,
                        datasets: [{
                            label: 'All Sales',
                            data: originalSales,
                            borderColor: 'green',
                            borderWidth: 2,
                            pointRadius: 2,
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            title: { display: true, text: "All Sales Over Time" },
                            datalabels: {
                                anchor: 'end',
                                align: 'top',
                                color: '#000',
                                font: { weight: 'bold', size: 12 },
                                formatter: (value) => `₱${value.toFixed(2)}`
                            }
                        }
                    },
                    plugins: [ChartDataLabels]
                });
            }

            function updateSummaryBoxes() {
                if (originalDates.length === 0) return;

                // --- Daily Average ---
                const dailyData = {};
                originalDates.forEach((d, i) => {
                    dailyData[d] = (dailyData[d] || 0) + originalSales[i];
                });
                const avgDaily = Object.values(dailyData).reduce((a, b) => a + b, 0) / Object.keys(dailyData).length;

                // --- Weekly Average ---
                const weeklyData = {};
                function getISOWeekKey(dateString) {
                    const date = new Date(dateString);
                    const tmp = new Date(date.getTime());
                    tmp.setHours(0, 0, 0, 0);
                    tmp.setDate(tmp.getDate() + 3 - ((tmp.getDay() + 6) % 7));
                    const week1 = new Date(tmp.getFullYear(), 0, 4);
                    const weekNo = Math.round(((tmp.getTime() - week1.getTime()) / 86400000 - 3 + ((week1.getDay() + 6) % 7)) / 7) + 1;
                    return `${tmp.getFullYear()}-W${String(weekNo).padStart(2, "0")}`;
                }

                originalDates.forEach((d, i) => {
                    const weekKey = getISOWeekKey(d);
                    weeklyData[weekKey] = (weeklyData[weekKey] || 0) + originalSales[i];
                });
                const avgWeekly = Object.values(weeklyData).reduce((a, b) => a + b, 0) / Object.keys(weeklyData).length;

                // --- Monthly Average ---
                const monthlyData = {};
                originalDates.forEach((d, i) => {
                    const monthKey = d.slice(0, 7);
                    monthlyData[monthKey] = (monthlyData[monthKey] || 0) + originalSales[i];
                });
                const avgMonthly = Object.values(monthlyData).reduce((a, b) => a + b, 0) / Object.keys(monthlyData).length;


                // --- Update DOM ---
                document.getElementById("dailyTotal").innerText = `₱${avgDaily.toFixed(2)}`;
                document.getElementById("weeklyTotal").innerText = `₱${avgWeekly.toFixed(2)}`;
                document.getElementById("monthlyTotal").innerText = `₱${avgMonthly.toFixed(2)}`;
            }


            function highlightSalesDates(dObj, dStr, fp, dayElem) {
                const localDate = dayElem.dateObj.getFullYear() + "-" +
                    String(dayElem.dateObj.getMonth() + 1).padStart(2, "0") + "-" +
                    String(dayElem.dateObj.getDate()).padStart(2, "0");

                if (originalDates.includes(localDate)) {
                    dayElem.style.backgroundColor = "#4CAF50";
                    dayElem.style.color = "#fff";
                    dayElem.style.borderRadius = "50%";
                    dayElem.style.width = "32px";
                    dayElem.style.height = "32px";
                    dayElem.style.display = "flex";
                    dayElem.style.alignItems = "center";
                    dayElem.style.justifyContent = "center";
                    dayElem.style.fontWeight = "bold";
                }
            }

            flatpickr("#startDate", {
                dateFormat: "Y-m-d",
                onDayCreate: highlightSalesDates
            });

            flatpickr("#endDate", {
                dateFormat: "Y-m-d",
                onDayCreate: highlightSalesDates
            });

            window.onload = function () {
                document.getElementById("forecastDays").value = 3;
                showAllSales();
                showInputSet("daily");
                updateSummaryBoxes();
            };
        </script>
    </body>

    </html>
    <?php
} else {
    header("Location: login.php");
    exit();
}
?>
