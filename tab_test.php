<?php
// Simplified version with only the essential code
$recordId = isset($_GET['id']) ? intval($_GET['id']) : 123; // Default ID for testing
?>

<!DOCTYPE html>
<html>
<head>
    <title>Tab Test</title>
    <style>
        /* Simple styles for testing */
        .tab { padding: 10px; cursor: pointer; background: #eee; display: inline-block; }
        .tab.active { background: #ddd; font-weight: bold; }
        .tab-content { display: none; padding: 20px; border: 1px solid #ddd; margin-top: 10px; }
        .tab-content.active { display: block; }
    </style>
</head>
<body>
    <div id="container-<?php echo $recordId; ?>" class="card-container">
        <!-- Tabs with only the most basic attributes -->
        <div class="tabs">
            <div class="tab active" onclick="tabClick(<?php echo $recordId; ?>, 'basic')">Basic</div>
            <div class="tab" onclick="tabClick(<?php echo $recordId; ?>, 'employment')">Employment</div>
            <div class="tab" onclick="tabClick(<?php echo $recordId; ?>, 'plc')">PLC</div>
        </div>

        <!-- Content areas -->
        <div id="basic-<?php echo $recordId; ?>" class="tab-content active">
            <h3>Basic Content</h3>
            <p>This is the basic tab content.</p>
        </div>

        <div id="employment-<?php echo $recordId; ?>" class="tab-content">
            <h3>Employment Content</h3>
            <p>This is the employment tab content.</p>
        </div>

        <div id="plc-<?php echo $recordId; ?>" class="tab-content">
            <h3>PLC Content</h3>
            <p>This is the PLC tab content.</p>
        </div>
    </div>

    <!-- The simplest possible JavaScript -->
    <script>
    function tabClick(id, tabType) {
        // Log what's happening
        console.log('Tab click function called');
        console.log('ID:', id, 'Tab type:', tabType);
        
        // Get the container
        const container = document.getElementById('container-' + id);
        
        // Get all tabs and contents
        const tabs = container.querySelectorAll('.tab');
        const contents = container.querySelectorAll('.tab-content');
        
        // Log what we found
        console.log('Found tabs:', tabs.length);
        console.log('Found contents:', contents.length);
        
        // Update tabs - remove active class from all, add to clicked
        tabs.forEach(tab => tab.classList.remove('active'));
        event.target.classList.add('active');
        
        // Update contents - hide all, show selected
        contents.forEach(content => content.classList.remove('active'));
        
        // Find and show target content
        const targetContent = document.getElementById(tabType + '-' + id);
        if (targetContent) {
            targetContent.classList.add('active');
            console.log('Activated content:', tabType + '-' + id);
        } else {
            console.error('Could not find content:', tabType + '-' + id);
        }
        
        // Prevent default and stop propagation
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        
        return false;
    }
    </script>
</body>
</html>