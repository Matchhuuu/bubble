<?php
session_start();

if (isset($_SESSION['ACC_ID']) && isset($_SESSION['EMAIL'])) {
    include "db_conn.php";
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
        <link rel="stylesheet" href="/fonts/fonts.css">
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
                min-height: 100vh;
                padding-top: 80px;
            }

            .navbar {
                background-color: #7e5832;
                width: 100%;
                height: 80px;
                position: fixed;
                top: 0;
                left: 0;
                z-index: 100;
                box-shadow: 0px 5px 11px 1px rgba(0, 0, 0, 0.28);
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0 40px;
            }

            .navbar-left {
                display: flex;
                align-items: center;
                gap: 20px;
            }

            .navbar-right {
                display: flex;
                align-items: center;
                gap: 20px;
            }

            .btn {
                font-family: 'Poppins', sans-serif;
                font-weight: bold;
                color: #f0f0f0;
                height: 40px;
                padding: 0 24px;
                background-color: #337609;
                border: none;
                border-radius: 25px;
                cursor: pointer;
                transition: 0.5s;
                font-size: 14px;
                box-shadow: 3px 4px 11px 1px rgba(0, 0, 0, 0.28);
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
            }

            .dropdown-content {
                display: none;
                position: absolute;
                background: white;
                min-width: 150px;
                box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
                z-index: 1;
                top: 100%;
                right: 0;
                border-radius: 12px;
                margin-top: 8px;
                overflow: hidden;
            }

            .dropdown-content a {
                color: #333;
                padding: 14px 20px;
                text-decoration: none;
                display: block;
                font-family: 'Poppins', sans-serif;
                font-size: 14px;
                transition: all 0.2s ease;
            }

            .dropdown-content a:hover {
                background: #f0f0f0;
                color: #667eea;
            }

            .show {
                display: block !important;
            }

            .container {
                display: flex;
                gap: 24px;
                padding: 30px 40px;
                max-width: 1600px;
                margin: 0 auto;
            }

            #left {
                flex: 1;
                min-width: 0;
            }

            #right {
                width: 380px;
                background: white;
                padding: 28px;
                border-radius: 16px;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
                overflow-y: auto;
                max-height: calc(100vh - 140px);
            }

            h2 {
                color: #2d3748;
                font-size: 28px;
                margin-bottom: 24px;
                font-weight: 700;
            }

            h4 {
                color: #2d3748;
                font-size: 14px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                margin-bottom: 16px;
                margin-top: 24px;
            }

            h4:first-child {
                margin-top: 0;
            }

            label {
                display: block;
                color: #4a5568;
                font-size: 13px;
                font-weight: 600;
                margin-bottom: 8px;
                text-transform: uppercase;
                letter-spacing: 0.3px;
            }

            canvas {
                background: white;
                padding: 20px;
                border-radius: 16px;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
                margin-bottom: 24px;
            }

            input,
            select {
                width: 100%;
                padding: 12px 14px;
                margin-top: 8px;
                margin-bottom: 16px;
                box-sizing: border-box;
                font-family: 'Poppins', sans-serif;
                border: 2px solid #e2e8f0;
                border-radius: 10px;
                font-size: 14px;
                transition: all 0.3s ease;
                color: #2d3748;
            }

            input:focus,
            select:focus {
                outline: none;
                border-color: #667eea;
                box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            }

            .summary-boxes {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 16px;
                margin-bottom: 28px;
            }

            .summary-boxes .box {
                background: white;
                border-radius: 16px;
                padding: 24px;
                text-align: center;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
                border-left: 5px solid #667eea;
                transition: all 0.3s ease;
            }

            .summary-boxes .box:hover {
                transform: translateY(-4px);
                box-shadow: 0 15px 50px rgba(0, 0, 0, 0.12);
            }

            .summary-boxes .daily {
                border-left-color: green;
            }

            .summary-boxes .weekly {
                border-left-color: blue;
            }

            .summary-boxes .monthly {
                border-left-color: orange;
            }

            .summary-boxes .box h2 {
                font-size: 26px;
                margin: 0 0 8px;
                margin-bottom: 8px;
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

            .summary-boxes .box p {
                margin: 0;
                font-size: 12px;
                color: #718096;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.3px;
            }

            .date-container {
                display: flex;
                gap: 12px;
                flex-wrap: wrap;
            }

            .date-group {
                flex: 1;
                min-width: 120px;
            }

            .date-group1 {
                width: 100%;
            }

            .chart-buttons {
                display: flex;
                gap: 8px;
                margin: 16px 0;
                flex-wrap: wrap;
            }

            .chart-toggle {
                flex: 1;
                min-width: 120px;
                padding: 12px 16px;
                background-color: #e0e0e0;
                color: #555;
                border: none;
                border-radius: 20px;
                font-family: 'Poppins', sans-serif;
                font-weight: 600;
                font-size: 13px;
                cursor: pointer;
                box-shadow: 2px 3px 8px rgba(0, 0, 0, 0.2);
                transition: background-color 0.3s;
                text-transform: none;
                letter-spacing: 0px;
            }

            .chart-toggle:hover {
                background-color: #d0d0d0;
                transition: background-color 0.3s;
            }

            .chart-toggle.active {
                background-color: #4CAF50;
                color: white;
                box-shadow: 2px 3px 8px rgba(76, 175, 80, 0.3);
            }

            .primary-btn {
                width: 100%;
                padding: 14px;
                margin-top: 16px;
                font-family: 'Poppins', sans-serif;
                background-color: #4CAF50;
                color: white;
                border: none;
                border-radius: 10px;
                font-weight: 600;
                font-size: 14px;
                cursor: pointer;
                box-shadow: 2px 3px 8px rgba(0, 0, 0, 0.2);
                transition: background-color 0.3s;
                text-transform: none;
                letter-spacing: 0px;
            }

            .primary-btn:hover {
                background-color: #3e8e41;
                transition: background-color 0.3s;
            }

            .secondary-btn {
                width: 100%;
                padding: 14px;
                margin-top: 10px;
                font-family: 'Poppins', sans-serif;
                background: white;
                color: #667eea;
                border: 2px solid #667eea;
                border-radius: 10px;
                font-weight: 600;
                font-size: 14px;
                cursor: pointer;
                transition: all 0.3s ease;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .secondary-btn:hover {
                background: #667eea;
                color: white;
                transform: translateY(-2px);
            }

            ::-webkit-scrollbar {
                width: 8px;
            }

            ::-webkit-scrollbar-track {
                background: #f1f1f1;
                border-radius: 10px;
            }

            ::-webkit-scrollbar-thumb {
                background: #cbd5e0;
                border-radius: 10px;
            }

            ::-webkit-scrollbar-thumb:hover {
                background: #a0aec0;
            }

            @media (max-width: 1024px) {
                .container {
                    flex-direction: column;
                    gap: 20px;
                    padding: 20px;
                }

                #right {
                    width: 100%;
                    max-height: none;
                }

                .summary-boxes {
                    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                }

                .navbar {
                    padding: 0 20px;
                    height: 70px;
                }

                body {
                    padding-top: 70px;
                }

                .chart-buttons {
                    flex-direction: column;
                }

                .chart-toggle {
                    min-width: auto;
                    width: 100%;
                }
            }
        </style>
    </head>

    <body>
        <div class="navbar">
            <div class="navbar-left">
                <form action="/interface/admin_homepage.php" style="margin: 0;">
                    <button type="submit" class="btn">← Back</button>
                </form>
            </div>
            <div class="navbar-right">
                <div class="dropdown">
                    <button onclick="toggleDropdown()" class="dropbtn">Admin</button>
                    <div id="myDropdown" class="dropdown-content">
                        <a href="/interface/logout.php">Logout</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="container">
            <div id="left">
                <h2>Sales Forecast Dashboard</h2>

                <div id="salesSummary" class="summary-boxes">
                    <div class="box daily">
                        <h2 id="dailyTotal">₱0</h2>
                        <p>Average Daily Sales</p>
                    </div>
                    <div class="box weekly">
                        <h2 id="weeklyTotal">₱0</h2>
                        <p>Average Weekly Sales</p>
                    </div>
                    <div class="box monthly">
                        <h2 id="monthlyTotal">₱0</h2>
                        <p>Average Monthly Sales</p>
                    </div>
                </div>

                <canvas id="forecastChart" height="200"></canvas>
            </div>

            <div id="right">
                <h4>Forecast Settings</h4>

                <label for="filterType">Select View</label>
                <select id="filterType" onchange="applyFilter()">
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly</option>
                    <option value="monthly">Monthly</option>
                </select>

                <h4 style="margin-top: 20px;">Forecast Type</h4>
                <div class="chart-buttons">
                    <button id="btnForecast" class="chart-toggle active">Daily</button>
                    <button id="btnForecastWeekly" class="chart-toggle">Weekly</button>
                    <button id="btnForecastMonthly" class="chart-toggle">Monthly</button>
                </div>

                <div id="dailyInputs">
                    <label for="startDate">Start Date</label>
                    <input type="text" id="startDate" placeholder="YYYY-MM-DD" />

                    <label for="endDate">End Date</label>
                    <input type="text" id="endDate" placeholder="YYYY-MM-DD" />

                    <label for="forecastDays">Days to Forecast</label>
                    <input type="number" id="forecastDays" min="1" max="14" placeholder="e.g., 3">
                </div>

                <button class="primary-btn" onclick="handleForecastClick()">Generate Forecast</button>
                <button class="secondary-btn" onclick="showAllSales()">Show All Sales</button>
            </div>
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
                    document.getElementById("dailyInputs").style.display = "block";
                } else {
                    document.getElementById("dailyInputs").style.display = "none";
                    customInputsContainer = document.createElement("div");
                    customInputsContainer.classList.add("date-container");
                    customInputsContainer.style.marginTop = "16px";

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
                                <input type="number" id="forecastWeeks" min="1" max="8" placeholder="e.g., 2">
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
                                <input type="number" id="forecastMonths" min="1" max="6" placeholder="e.g., 2">
                            </div>
                        `;
                    }
                    const forecastMethod = document.querySelector('[for="forecastMethod"]');
                    if (forecastMethod) {
                        forecastMethod.parentNode.insertBefore(customInputsContainer, forecastMethod);
                    } else {
                        document.getElementById("right").insertBefore(customInputsContainer, document.getElementById("right").lastElementChild);
                    }
                }
            }


            function generateWeeklyForecast() {
                const startWeek = document.getElementById("startWeek").value;
                const endWeek = document.getElementById("endWeek").value;
                const forecastWeeks = parseInt(document.getElementById("forecastWeeks").value);

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
                plotDualForecast(weeks, sales, forecastWeeks, "Weekly");
            }

            function generateMonthlyForecast() {
                const startMonth = document.getElementById("startMonth").value;
                const endMonth = document.getElementById("endMonth").value;
                const forecastMonths = parseInt(document.getElementById("forecastMonths").value);

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
                plotDualForecast(months, sales, forecastMonths, "Monthly");
            }


            function plotDualForecast(labels, values, steps, type) {
                // Generate SMA Forecast
                let smaForecast = [];
                let smaCombined = [...values];
                const windowSize = 3;
                for (let i = 0; i < steps; i++) {
                    const avg = smaCombined.slice(-windowSize).reduce((a, b) => a + b, 0) / windowSize;
                    smaForecast.push(avg);
                    smaCombined.push(avg);
                }

                // Generate Exponential Forecast
                let expForecast = [];
                const alpha = 0.5, beta = 0.3;
                let level = values[0], trend = values[1] - values[0];
                for (let i = 1; i < values.length; i++) {
                    let prevLevel = level;
                    level = alpha * values[i] + (1 - alpha) * (level + trend);
                    trend = beta * (level - prevLevel) + (1 - beta) * trend;
                }
                for (let i = 0; i < steps; i++) expForecast.push(level + (i + 1) * trend);

                const forecastLabels = Array.from({ length: steps }, (_, i) => `${type} +${i + 1}`);
                const finalLabels = [...labels, ...forecastLabels];

                if (chart) chart.destroy();
                chart = new Chart(ctx, {
                    type: "line",
                    data: {
                        labels: finalLabels,
                        datasets: [
                            {
                                label: `${type} SMA Forecast`,
                                data: [...values, ...smaForecast],
                                borderColor: '#10b981',
                                backgroundColor: 'rgba(16, 185, 129, 0.05)',
                                borderWidth: 3,
                                tension: 0.4,
                                pointRadius: 5,
                                pointBackgroundColor: '#10b981',
                                pointBorderColor: '#10b981',
                                pointBorderWidth: 2,
                                segment: { borderDash: ctx => ctx.p0DataIndex < values.length - 1 ? undefined : [5, 5] }
                            },
                            {
                                label: `${type} Exponential Forecast`,
                                data: [...values, ...expForecast],
                                borderColor: '#667eea',
                                backgroundColor: 'rgba(102, 126, 234, 0.05)',
                                borderWidth: 3,
                                tension: 0.4,
                                pointRadius: 5,
                                pointBackgroundColor: '#667eea',
                                pointBorderColor: '#667eea',
                                pointBorderWidth: 2,
                                segment: { borderDash: ctx => ctx.p0DataIndex < values.length - 1 ? undefined : [5, 5] }
                            }
                        ]
                    },
                    options: {
                        plugins: {
                            title: { display: true, text: `Dual ${type} Forecast - SMA vs Exponential (${steps} ${type === "Weekly" ? "Weeks" : "Months"})`, font: { size: 16, weight: 'bold' }, color: '#2d3748' },
                            datalabels: {
                                anchor: 'end',
                                align: 'top',
                                color: '#000',
                                font: { weight: 'bold', size: 11 },
                                formatter: (v) => `₱${v.toFixed(0)}`
                            }
                        },
                        scales: { y: { beginAtZero: true, ticks: { color: '#718096' }, grid: { color: 'rgba(0, 0, 0, 0.05)' } }, x: { ticks: { color: '#718096' }, grid: { color: 'rgba(0, 0, 0, 0.05)' } } },
                        responsive: true,
                        maintainAspectRatio: true
                    },
                    plugins: [ChartDataLabels]
                });
            }


            function generateSMAForecast() {
                const start = document.getElementById("startDate").value;
                const end = document.getElementById("endDate").value;
                const forecastDays = parseInt(document.getElementById("forecastDays").value);

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

                if (salesRange.length < 3) {
                    alert("At least 3 data points are needed to compute the forecasts.");
                    return;
                }

                // Generate SMA Forecast
                let smaForecast = [];
                let smaHybrid = [...salesRange];
                const windowSize = 3;
                for (let i = 0; i < forecastDays; i++) {
                    const lastWindow = smaHybrid.slice(-windowSize);
                    const sma = parseFloat((lastWindow.reduce((a, b) => a + b, 0) / windowSize).toFixed(2));
                    smaForecast.push(sma);
                    smaHybrid.push(sma);
                }

                // Generate Exponential Forecast
                let expForecast = [];
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
                    expForecast.push(parseFloat((level + (i + 1) * trend).toFixed(2)));
                }

                const forecastedLabels = Array.from({ length: forecastDays }, (_, i) => `Day +${i + 1}`);
                const combinedLabels = [...dateRange, ...forecastedLabels];

                if (chart) chart.destroy();
                chart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: combinedLabels,
                        datasets: [
                            {
                                label: `Sales + ${forecastDays}-Day SMA Forecast`,
                                data: [...salesRange, ...smaForecast],
                                borderColor: '#10b981',
                                backgroundColor: 'rgba(16, 185, 129, 0.05)',
                                borderWidth: 3,
                                pointRadius: 4,
                                tension: 0.4,
                                spanGaps: true,
                                segment: {
                                    borderDash: ctx => ctx.p0DataIndex < salesRange.length - 1 ? undefined : [5, 5]
                                }
                            },
                            {
                                label: `Sales + ${forecastDays}-Day Exponential Forecast`,
                                data: [...salesRange, ...expForecast],
                                borderColor: '#667eea',
                                backgroundColor: 'rgba(102, 126, 234, 0.05)',
                                borderWidth: 3,
                                pointRadius: 4,
                                tension: 0.4,
                                spanGaps: true,
                                segment: {
                                    borderDash: ctx => ctx.p0DataIndex < salesRange.length - 1 ? undefined : [5, 5]
                                }
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            title: {
                                display: true,
                                text: `Dual Forecast - SMA vs Exponential Smoothing (${forecastDays} Days) from ${end}`,
                                font: { size: 16, weight: 'bold' },
                                color: '#2d3748'
                            },
                            datalabels: {
                                anchor: 'end',
                                align: 'top',
                                color: '#000',
                                font: { weight: 'bold', size: 11 },
                                formatter: (value) => `₱${value.toFixed(0)}`
                            }
                        },
                        scales: { y: { beginAtZero: true, ticks: { color: '#718096' }, grid: { color: 'rgba(0, 0, 0, 0.05)' } }, x: { ticks: { color: '#718096' }, grid: { color: 'rgba(0, 0, 0, 0.05)' } } }
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
                            borderColor: '#f59e0b',
                            backgroundColor: 'rgba(245, 158, 11, 0.05)',
                            borderWidth: 3,
                            pointRadius: 4,
                            tension: 0.4,
                            pointBackgroundColor: '#f59e0b'
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            title: {
                                display: true,
                                text: `Sales Data (${filterType.charAt(0).toUpperCase() + filterType.slice(1)})`,
                                font: { size: 16, weight: 'bold' },
                                color: '#2d3748'
                            },
                            datalabels: {
                                anchor: 'end',
                                align: 'top',
                                color: '#000',
                                font: { weight: 'bold', size: 11 },
                                formatter: (value) => `₱${value.toLocaleString()}`
                            }
                        },
                        scales: { y: { beginAtZero: true, ticks: { color: '#718096' }, grid: { color: 'rgba(0, 0, 0, 0.05)' } }, x: { ticks: { color: '#718096' }, grid: { color: 'rgba(0, 0, 0, 0.05)' } } }
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
                            borderColor: '#667eea',
                            backgroundColor: 'rgba(102, 126, 234, 0.05)',
                            borderWidth: 3,
                            pointRadius: 3,
                            tension: 0.4,
                            pointBackgroundColor: '#667eea'
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            title: { display: true, text: "All Sales Over Time", font: { size: 16, weight: 'bold' }, color: '#2d3748' },
                            datalabels: {
                                anchor: 'end',
                                align: 'top',
                                color: '#000',
                                font: { weight: 'bold', size: 10 },
                                formatter: (value) => `₱${value.toFixed(0)}`
                            }
                        },
                        scales: { y: { beginAtZero: true, ticks: { color: '#718096' }, grid: { color: 'rgba(0, 0, 0, 0.05)' } }, x: { ticks: { color: '#718096' }, grid: { color: 'rgba(0, 0, 0, 0.05)' } } }
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
                    dayElem.style.backgroundColor = "#667eea";
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
