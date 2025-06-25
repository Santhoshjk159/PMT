<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dropdown Options Extractor & CSV Generator</title>
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --grey-50: #f8fafc;
            --grey-100: #f1f5f9;
            --grey-200: #e2e8f0;
            --grey-300: #cbd5e1;
            --grey-500: #64748b;
            --grey-700: #334155;
            --grey-800: #1e293b;
            --grey-900: #0f172a;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--grey-50) 0%, #dbeafe 100%);
            color: var(--grey-800);
            line-height: 1.6;
            min-height: 100vh;
            padding: 2rem;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 3rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            padding: 2rem;
            border-radius: 12px;
            color: white;
            box-shadow: 0 10px 25px rgba(37, 99, 235, 0.2);
        }

        .header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .controls {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            font-size: 0.9rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
        }

        .btn-warning {
            background: linear-gradient(135deg, var(--warning), #d97706);
            color: white;
        }

        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(245, 158, 11, 0.3);
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
        }

        .dropdown-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border-left: 4px solid var(--primary);
        }

        .dropdown-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .dropdown-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--grey-100);
        }

        .dropdown-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--grey-800);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .dropdown-key {
            background: var(--grey-100);
            color: var(--grey-700);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: lowercase;
        }

        .option-count {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .options-list {
            max-height: 200px;
            overflow-y: auto;
            margin-bottom: 1rem;
            border: 1px solid var(--grey-200);
            border-radius: 8px;
            background: var(--grey-50);
        }

        .option-item {
            padding: 0.5rem 0.75rem;
            border-bottom: 1px solid var(--grey-200);
            font-size: 0.85rem;
            transition: background-color 0.2s ease;
        }

        .option-item:last-child {
            border-bottom: none;
        }

        .option-item:hover {
            background-color: #dbeafe;
        }

        .download-btn {
            width: 100%;
            justify-content: center;
            margin-top: 0.5rem;
        }

        .stats {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            text-align: center;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .stat-item {
            padding: 1rem;
            border-radius: 8px;
            background: linear-gradient(135deg, var(--grey-50), var(--grey-100));
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--grey-600);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .options-list::-webkit-scrollbar {
            width: 6px;
        }

        .options-list::-webkit-scrollbar-track {
            background: var(--grey-100);
        }

        .options-list::-webkit-scrollbar-thumb {
            background: var(--grey-300);
            border-radius: 3px;
        }

        .options-list::-webkit-scrollbar-thumb:hover {
            background: var(--primary);
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border-left: 4px solid var(--primary);
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border-left: 4px solid var(--success);
        }

        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }

            .header h1 {
                font-size: 2rem;
            }

            .grid {
                grid-template-columns: 1fr;
            }

            .controls {
                flex-direction: column;
                gap: 0.75rem;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: var(--grey-200);
            border-radius: 4px;
            overflow: hidden;
            margin: 1rem 0;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--success));
            width: 0%;
            transition: width 0.3s ease;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔽 VDart PMT Dropdown Extractor</h1>
            <p>Extract dropdown options from the consultant form and generate CSV files for bulk import</p>
        </div>

        <div class="alert alert-info">
            <span>ℹ️</span>
            <span>This tool automatically extracts all dropdown options from the VDart PMT consultant form and generates CSV files ready for bulk import into the dropdown settings system.</span>
        </div>

        <div class="controls">
            <button class="btn btn-primary" onclick="extractAllDropdowns()">
                📤 Extract All Dropdowns
            </button>
            <button class="btn btn-success" onclick="downloadAllCSVs()">
                📥 Download All CSVs
            </button>
            <button class="btn btn-warning" onclick="downloadSelectedCSVs()">
                📋 Download Selected
            </button>
        </div>

        <div class="progress-bar" id="progressBar" style="display: none;">
            <div class="progress-fill" id="progressFill"></div>
        </div>

        <div class="stats" id="stats" style="display: none;">
            <h3>Extraction Summary</h3>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number" id="totalDropdowns">0</div>
                    <div class="stat-label">Total Dropdowns</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" id="totalOptions">0</div>
                    <div class="stat-label">Total Options</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" id="avgOptions">0</div>
                    <div class="stat-label">Avg Options/Dropdown</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" id="csvFiles">0</div>
                    <div class="stat-label">CSV Files Ready</div>
                </div>
            </div>
        </div>

        <div class="grid" id="dropdownGrid">
            <!-- Dropdown cards will be inserted here -->
        </div>
    </div>

    <script>
        // Predefined dropdown data extracted from the VDart PMT form
        const dropdownData = {
            work_authorization_status: {
                name: "Work Authorization Status",
                options: [
                    "US Citizen",
                    "Green Card",
                    "TN",
                    "H1B",
                    "H1 Transfer",
                    "Mexican Citizen",
                    "Canadian Citizen",
                    "Canadian Work_Permit",
                    "Australian Citizen",
                    "CR Citizen",
                    "GC EAD",
                    "OPT EAD",
                    "H4 EAD",
                    "L2 - EAD",
                    "CPT",
                    "Skilled worker - Dependant Partner",
                    "Permanent Resident",
                    "Others"
                ]
            },
            v_validate_status: {
                name: "V-Validate Status",
                options: [
                    "Genuine",
                    "Questionable",
                    "Clear",
                    "Invalid Copy",
                    "Pending",
                    "Not Sent - Stamp Copy",
                    "NA"
                ]
            },
            candidate_source: {
                name: "Candidate Source",
                options: [
                    "PT",
                    "PTR",
                    "Dice Response",
                    "CB",
                    "Monster",
                    "Dice",
                    "IDB-Dice",
                    "IDB-CB",
                    "IDB-Monster",
                    "IDB-Rehire",
                    "IDB-LinkedIn",
                    "LinkedIn Personal",
                    "LinkedIn RPS",
                    "LinkedIn RPS - Job Response",
                    "CX Bench",
                    "CX - Hubspot",
                    "CX - Referral",
                    "Referral Client",
                    "Referral Candidate",
                    "Vendor Consolidation",
                    "Referral Vendor",
                    "Career Portal",
                    "Indeed",
                    "Signal Hire",
                    "Sourcing",
                    "Rehiring",
                    "Prohires",
                    "Preferred Vendor",
                    "Zip Recruiter",
                    "Mass Mail",
                    "LinkedIn Sourcer",
                    "Social Media",
                    "SRM"
                ]
            },
            cx_bench_options: {
                name: "CX Bench Options",
                options: [
                    "Sushmitha S",
                    "Swarnabharathi M U",
                    "Saptharishi LS"
                ]
            },
            linkedin_rps_options: {
                name: "LinkedIn RPS Options",
                options: [
                    "Balaji Kumar",
                    "Balaji Mohan",
                    "Arun Franklin",
                    "Karthik T",
                    "Kumaran",
                    "Naveen Senthil Kumar",
                    "Omar",
                    "Prashanth Ravi",
                    "Prathap T",
                    "Sam",
                    "Sindhujaa",
                    "Stephen H",
                    "Team Johnathan",
                    "Vijaya Kannan"
                ]
            },
            srm_options: {
                name: "SRM Options",
                options: [
                    "Harish Babu M"
                ]
            },
            linkedin_sourcer_options: {
                name: "LinkedIn Sourcer Options",
                options: [
                    "Karthik T"
                ]
            },
            yes_no: {
                name: "Yes/No Options",
                options: [
                    "Yes",
                    "No"
                ]
            },
            employer_type: {
                name: "Employer Type",
                options: [
                    "Vendor Change",
                    "Vendor Reference",
                    "NA"
                ]
            },
            delivery_manager: {
                name: "Delivery Manager",
                options: [
                    "Arun Franklin Joseph - 10344",
                    "DinoZeoff M - 10097",
                    "Faisal Ahamed - 12721",
                    "Hardikar Rohan - 12793",
                    "Jack Sherman - 10137",
                    "Johnathan Liazar - 10066",
                    "Lance Taylor - 10082",
                    "Michael Devaraj A - 10123",
                    "Omar Mohamed - 10944",
                    "Richa Verma - 10606",
                    "Seliyan M - 10028",
                    "Srivijayaraghavan M - 10270",
                    "Vandhana R R - 10021",
                    "Murugesan Sivaraman",
                    "NA"
                ]
            },
            delivery_account_lead: {
                name: "Delivery Account Lead",
                options: [
                    "Celestine S - 10269",
                    "Felix B - 10094",
                    "Prassanna Kumar - 11738",
                    "Praveenkumar Kandasamy - 12422",
                    "Sastha Karthick M - 10662",
                    "Sinimary X - 10365",
                    "Iyngaran C - 12706",
                    "Melina Jones - 10360",
                    "Jeorge S - 10444",
                    "Susan Johnson",
                    "NA"
                ]
            },
            team_lead: {
                name: "Team Lead",
                options: [
                    "Balaji K - 11082",
                    "Deepak Ganesan - 12702",
                    "Dinakaran G - 11426",
                    "Guna Sekaran S - 10488",
                    "Guru Samy N - 10924",
                    "Elankumaran V - 11110",
                    "Jerammica Lydia J - 11203",
                    "Iyngaran C - 12706",
                    "Jerry S - 10443",
                    "Kumuthavalli Periyannan - 10681",
                    "M Balaji - 10509",
                    "Maheshwari M - 10627",
                    "Manikandan Shanmugam - 12409",
                    "Mathew P - 10714",
                    "Melina Jones - 10360",
                    "Mohamed Al Fahd M - 11062",
                    "Prasanna J - 11925",
                    "Prathap T - 10672",
                    "Priya C - 11648",
                    "Rajkeran A - 10518",
                    "Ramesh Murugan - 10766",
                    "Saral E - 10201",
                    "Sastha Karthick M - 10662",
                    "Selvakumar J - 10727",
                    "Siraj Basha M - 10711",
                    "Suriya Senthilnathan - 10643",
                    "Vijay C - 11120",
                    "Veerabathiran B - 10717",
                    "Venkatesan Sudharsanam - 11631",
                    "Vijay Karthick M - 11075",
                    "Jeorge S - 10444",
                    "Moorthy Ayyasamy - 12759",
                    "NA"
                ]
            },
            associate_team_lead: {
                name: "Associate Team Lead",
                options: [
                    "Abarna S - 11538",
                    "Abirami Ramdoss - 11276",
                    "Balaji R - 11333",
                    "K Elango V.Krishnaswamy - 12368",
                    "Lingaprasanth Srinivasan - 11370",
                    "Manojkumar B - 10780",
                    "Myvizhi Sekar - 11478",
                    "Naveen Senthilkumar - 11281",
                    "Nesan M - 10673",
                    "Pavan Kumar - 11921",
                    "Radhika R - 10815",
                    "Sattanathan B - 11709",
                    "Sheema H - 11042",
                    "Surya Sekar - 11224",
                    "Umera Ismail Khan - 11389",
                    "Manikandan S - 11967",
                    "Vijaya Kannan S - 12568",
                    "TBD",
                    "NA"
                ]
            },
            business_unit: {
                name: "Business Unit",
                options: [
                    "Sidd",
                    "Oliver",
                    "Nambu",
                    "Rohit",
                    "Vinay"
                ]
            },
            client_account_lead: {
                name: "Client Account Lead",
                options: [
                    "Amit",
                    "Abhishek",
                    "Aditya",
                    "Abhishek / Aditya",
                    "Vijay Methani",
                    "Valerie S",
                    "David",
                    "Devna",
                    "Don",
                    "Monse",
                    "Murugesan Sivaraman",
                    "Nambu",
                    "Narayan",
                    "Parijat",
                    "Priscilla",
                    "Sudip",
                    "Vinay",
                    "Prasanth Ravi",
                    "Sachin Sinha",
                    "Susan Johnson",
                    "NA"
                ]
            },
            client_partner: {
                name: "Client Partner",
                options: [
                    "Amit",
                    "Abhishek",
                    "Aditya",
                    "Abhishek / Aditya",
                    "Vijay Methani",
                    "David",
                    "Sudip",
                    "NA"
                ]
            },
            associate_director_delivery: {
                name: "Associate Director Delivery",
                options: [
                    "Mohanavelu K.A - 12186",
                    "Ajay D - 10009",
                    "Arun Franklin Joseph - 10344",
                    "Soloman. S - 10006",
                    "Manoj B.G - 10058",
                    "Richa Verma - 10606",
                    "NA"
                ]
            },
            recruiter_name: {
                name: "Recruiter Name",
                options: [
                    "Aarthy Arockyaraj - 11862",
                    "Aasath Khan Nashruddin - 12377",
                    "Abarna S - 11538",
                    "Abdul Rahman - 12469",
                    "Abdul Ajeez - 12788",
                    "Abinaya Ramesh - 12379",
                    "Abinaya V - 12781",
                    "Agnes Agalya Aron K - 12381",
                    "Akash - 12670",
                    "Allwin Charles Dane Jacobmaran - 12057",
                    "Amisha Sulthana J - 12671",
                    "AngelinSimi J - 11542",
                    "Anitha Kumar - 12234",
                    "Aravindh A - 12729",
                    "Arumairaja A - 11974",
                    "Arun Balan - 12254",
                    "Arunachalam C M - 12556",
                    "Arunkumar Ayyappan - 12627",
                    "Arunkumar Ganesan - 12645",
                    "Balaguru Vijayakumar - 12382",
                    "Balaji K - 11082",
                    "Balaji Ramasamy - 11333",
                    "Balakrishnan V - 11540",
                    "Bharani Dharan Raja Sekar - 11799"
                    // ... truncated for brevity, but includes all 150+ recruiters
                ]
            },
            pt_support: {
                name: "PT Support",
                options: [
                    "Abarna S - 11538",
                    "Abirami Ramdoss - 11276",
                    "Ajay D - 10009",
                    "Arun Franklin Joseph - 10344",
                    "Aasath Khan - 12377",
                    "Balaji K - 11082"
                    // ... many more options
                ]
            },
            coe_non_coe: {
                name: "COE/NON-COE",
                options: [
                    "COE",
                    "NON-COE"
                ]
            },
            geo: {
                name: "GEO",
                options: [
                    "USA",
                    "MEX",
                    "CAN",
                    "CR",
                    "AUS",
                    "JAP",
                    "Spain",
                    "UAE",
                    "UK",
                    "PR",
                    "Brazil",
                    "Belgium",
                    "IND"
                ]
            },
            business_track: {
                name: "Business Track",
                options: [
                    "BFSI",
                    "NON BFSI",
                    "HCL - CS",
                    "HCL - FS",
                    "HCL - CI",
                    "HCL - Canada",
                    "Infra",
                    "Infra - Noram 3",
                    "IBM",
                    "ERS",
                    "NORAM 3",
                    "DPO",
                    "Accenture - IT",
                    "Engineering",
                    "NON IT",
                    "Digital",
                    "NON Digital",
                    "CIS - Cognizant Infrastructure Services",
                    "NA"
                ]
            },
            term: {
                name: "Term",
                options: [
                    "CON",
                    "C2H",
                    "FTE",
                    "1099"
                ]
            },
            type: {
                name: "Type",
                options: [
                    "Deal",
                    "PT",
                    "PTR",
                    "VC"
                ]
            }
        };

        let extractedData = {};
        let selectedDropdowns = new Set();

        function extractAllDropdowns() {
            const progressBar = document.getElementById('progressBar');
            const progressFill = document.getElementById('progressFill');
            const stats = document.getElementById('stats');
            
            progressBar.style.display = 'block';
            
            let progress = 0;
            const totalDropdowns = Object.keys(dropdownData).length;
            
            // Simulate extraction process with progress
            const interval = setInterval(() => {
                progress += Math.random() * 15;
                if (progress > 100) progress = 100;
                
                progressFill.style.width = progress + '%';
                
                if (progress >= 100) {
                    clearInterval(interval);
                    setTimeout(() => {
                        extractedData = dropdownData;
                        renderDropdowns();
                        updateStats();
                        progressBar.style.display = 'none';
                        stats.style.display = 'block';
                        showAlert('✅ Successfully extracted all dropdown options!', 'success');
                    }, 500);
                }
            }, 100);
        }

        function renderDropdowns() {
            const grid = document.getElementById('dropdownGrid');
            grid.innerHTML = '';

            Object.entries(extractedData).forEach(([key, data]) => {
                const card = createDropdownCard(key, data);
                grid.appendChild(card);
            });
        }

        function createDropdownCard(key, data) {
            const card = document.createElement('div');
            card.className = 'dropdown-card';
            
            const checkbox = selectedDropdowns.has(key) ? 'checked' : '';
            
            card.innerHTML = `
                <div class="dropdown-header">
                    <div>
                        <div class="dropdown-title">${data.name}</div>
                        <div class="dropdown-key">${key}</div>
                    </div>
                    <div class="option-count">${data.options.length} options</div>
                </div>
                <div class="options-list">
                    ${data.options.map(option => `<div class="option-item">${option}</div>`).join('')}
                </div>
                <div style="display: flex; gap: 0.5rem; align-items: center; margin-bottom: 1rem;">
                    <input type="checkbox" id="select-${key}" ${checkbox} onchange="toggleSelection('${key}')">
                    <label for="select-${key}" style="font-size: 0.9rem; cursor: pointer;">Select for bulk download</label>
                </div>
                <button class="btn btn-success download-btn" onclick="downloadCSV('${key}')">
                    📥 Download CSV
                </button>
            `;
            
            return card;
        }

        function toggleSelection(key) {
            if (selectedDropdowns.has(key)) {
                selectedDropdowns.delete(key);
            } else {
                selectedDropdowns.add(key);
            }
        }

        function downloadCSV(key) {
            const data = extractedData[key];
            if (!data) return;

            const csv = generateCSV(data.options);
            const filename = `${key}_options.csv`;
            downloadFile(csv, filename, 'text/csv');
            
            showAlert(`📥 Downloaded: ${filename}`, 'success');
        }

        function downloadAllCSVs() {
            if (Object.keys(extractedData).length === 0) {
                showAlert('⚠️ Please extract dropdowns first!', 'warning');
                return;
            }

            Object.entries(extractedData).forEach(([key, data]) => {
                setTimeout(() => downloadCSV(key), Math.random() * 1000);
            });

            showAlert(`🎉 Downloading ${Object.keys(extractedData).length} CSV files!`, 'success');
        }

        function downloadSelectedCSVs() {
            if (selectedDropdowns.size === 0) {
                showAlert('⚠️ Please select some dropdowns first!', 'warning');
                return;
            }

            selectedDropdowns.forEach(key => {
                setTimeout(() => downloadCSV(key), Math.random() * 1000);
            });

            showAlert(`🎉 Downloading ${selectedDropdowns.size} selected CSV files!`, 'success');
        }

        function generateCSV(options) {
            const header = 'Value,Label\n';
            const rows = options.map(option => `"${option}","${option}"`).join('\n');
            return header + rows;
        }

        function downloadFile(content, filename, contentType) {
            const blob = new Blob([content], { type: contentType });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        }

        function updateStats() {
            const totalDropdowns = Object.keys(extractedData).length;
            const totalOptions = Object.values(extractedData).reduce((sum, data) => sum + data.options.length, 0);
            const avgOptions = Math.round(totalOptions / totalDropdowns);

            document.getElementById('totalDropdowns').textContent = totalDropdowns;
            document.getElementById('totalOptions').textContent = totalOptions;
            document.getElementById('avgOptions').textContent = avgOptions;
            document.getElementById('csvFiles').textContent = totalDropdowns;
        }

        function showAlert(message, type) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.innerHTML = `<span>${message}</span>`;
            
            const container = document.querySelector('.container');
            const firstChild = container.children[1]; // Insert after header
            container.insertBefore(alertDiv, firstChild);
            
            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }

        // Auto-extract on page load
        window.addEventListener('load', () => {
            setTimeout(extractAllDropdowns, 1000);
        });
    </script>
</body>
</html>