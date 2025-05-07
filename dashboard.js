document.addEventListener('DOMContentLoaded', function() {
    // Fetch data for dashboard
    fetchDashboardData();
    
    // Set up auto-refresh every 5 minutes
    setInterval(fetchDashboardData, 300000);
});

function fetchDashboardData() {
    fetch('dashboard_data.php')
        .then(response => response.json())
        .then(data => {
            updateMeatTypeChart(data.meatDistribution);
            updateLossStageChart(data.lossStages);
            updateSpoilageStats(data.spoilageStats);
            updateConditionAlerts(data.conditionAlerts);
            updateExpiringSoon(data.expiringSoon);
        })
        .catch(error => console.error('Error fetching dashboard data:', error));
}

// Meat Type Distribution Chart
function updateMeatTypeChart(data) {
    const meatTypeCtx = document.getElementById('meatTypeChart').getContext('2d');
    if (window.meatTypeChart) {
        window.meatTypeChart.destroy();
    }
    
    window.meatTypeChart = new Chart(meatTypeCtx, {
        type: 'doughnut',
        data: {
            labels: data.labels,
            datasets: [{
                data: data.values,
                backgroundColor: [
                    '#4e73df',
                    '#1cc88a',
                    '#36b9cc',
                    '#f6c23e',
                    '#e74a3b',
                    '#858796'
                ],
                hoverBackgroundColor: [
                    '#2e59d9',
                    '#17a673',
                    '#2c9faf',
                    '#dda20a',
                    '#be2617',
                    '#60616f'
                ]
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((value / total) * 100);
                            return `${label}: ${value}kg (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}

// Loss Stage Analysis Chart
function updateLossStageChart(data) {
    const lossStageCtx = document.getElementById('lossStageChart').getContext('2d');
    if (window.lossStageChart) {
        window.lossStageChart.destroy();
    }
    
    window.lossStageChart = new Chart(lossStageCtx, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Loss Quantity (kg)',
                data: data.values,
                backgroundColor: '#e74a3b'
            }]
        },
        options: {
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Quantity Lost (kg)'
                    }
                }
            }
        }
    });
}

// Update Spoilage Statistics
function updateSpoilageStats(stats) {
    document.getElementById('spoilagePercentage').textContent = stats.percentage + '%';
    document.getElementById('totalSpoiled').textContent = stats.totalSpoiled + ' kg';
    document.getElementById('totalInventory').textContent = stats.totalInventory + ' kg';
    
    const progressBar = document.querySelector('.progress-bar');
    progressBar.style.width = stats.percentage + '%';
    progressBar.setAttribute('aria-valuenow', stats.percentage);
    progressBar.textContent = stats.percentage + '%';
}

// Update Condition Alerts
function updateConditionAlerts(alerts) {
    const alertsContainer = document.getElementById('conditionAlerts');
    alertsContainer.innerHTML = '';
    
    if (alerts.length === 0) {
        alertsContainer.innerHTML = '<div class="alert alert-success">All storage conditions are normal</div>';
        return;
    }
    
    alerts.forEach(alert => {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${alert.severity === 'critical' ? 'danger' : 'warning'}`;
        
        let message = `<strong>${alert.location}</strong>: `;
        if (alert.temperatureStatus !== 'normal') {
            message += `Temperature ${alert.temperature}°C (${alert.temperatureStatus}), `;
        }
        if (alert.humidityStatus !== 'normal') {
            message += `Humidity ${alert.humidity}% (${alert.humidityStatus})`;
        }
        message += ` - Recorded at ${alert.recordedAt}`;
        
        alertDiv.innerHTML = message;
        alertsContainer.appendChild(alertDiv);
    });
}

// Update Expiring Soon List
function updateExpiringSoon(items) {
    const expiringList = document.getElementById('expiringSoonList');
    expiringList.innerHTML = '';
    
    if (items.length === 0) {
        expiringList.innerHTML = '<li class="list-group-item">No items expiring soon</li>';
        return;
    }
    
    items.forEach(item => {
        const listItem = document.createElement('li');
        listItem.className = 'list-group-item d-flex justify-content-between align-items-center';
        
        const daysLeft = Math.ceil((new Date(item.expiryDate) - new Date()) / (1000 * 60 * 60 * 24));
        const badgeClass = daysLeft <= 3 ? 'bg-danger' : (daysLeft <= 7 ? 'bg-warning' : 'bg-primary');
        
        listItem.innerHTML = `
            <div>
                <strong>${item.batchNumber}</strong> - ${item.meatType} (${item.quantity}kg)
                <br>
                <small class="text-muted">Expires: ${item.expiryDate}</small>
            </div>
            <span class="badge ${badgeClass}">${daysLeft} days</span>
        `;
        
        expiringList.appendChild(listItem);
    });
}

// Inventory form handling
document.getElementById('saveInventory').addEventListener('click', function() {
    const form = document.getElementById('inventoryForm');
    if (form.checkValidity()) {
        alert('Inventory saved successfully!');
        const modal = bootstrap.Modal.getInstance(document.getElementById('inventoryModal'));
        modal.hide();
        form.reset();
    } else {
        form.reportValidity();
    }
});