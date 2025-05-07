document.addEventListener('DOMContentLoaded', function() {
    // Initialize charts
    initCharts();
    
    // Set up event listeners
    document.getElementById('saveInventory').addEventListener('click', saveInventory);
    
    // Load initial data
    updateDashboard();
});

function initCharts() {
    // Meat Type Distribution Chart
    const meatTypeCtx = document.getElementById('meatTypeChart').getContext('2d');
    const meatTypeChart = new Chart(meatTypeCtx, {
        type: 'doughnut',
        data: {
            labels: ['Beef', 'Chicken', 'Pork', 'Lamb', 'Turkey', 'Other'],
            datasets: [{
                data: [35, 25, 20, 10, 5, 5],
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
                ],
                hoverBorderColor: "rgba(234, 236, 244, 1)",
            }],
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                tooltip: {
                    backgroundColor: "rgb(255,255,255)",
                    bodyFontColor: "#858796",
                    borderColor: '#dddfeb',
                    borderWidth: 1,
                    xPadding: 15,
                    yPadding: 15,
                    displayColors: false,
                    caretPadding: 10,
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((value / total) * 100);
                            return `${label}: ${value}kg (${percentage}%)`;
                        }
                    }
                },
                legend: {
                    position: 'right',
                    labels: {
                        usePointStyle: true,
                        padding: 20
                    }
                }
            },
            cutout: '70%',
        }
    });

    // Loss Stage Analysis Chart
    const lossStageCtx = document.getElementById('lossStageChart').getContext('2d');
    const lossStageChart = new Chart(lossStageCtx, {
        type: 'bar',
        data: {
            labels: ['Slaughter', 'Processing', 'Storage', 'Handling', 'Transport', 'Retail'],
            datasets: [{
                label: 'Loss Quantity (kg)',
                data: [15, 10, 8, 5, 7, 5],
                backgroundColor: '#e74a3b',
                hoverBackgroundColor: '#be2617',
                borderColor: '#e74a3b',
                borderWidth: 1
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
                },
                x: {
                    title: {
                        display: true,
                        text: 'Process Stage'
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `Loss: ${context.raw}kg`;
                        }
                    }
                },
                legend: {
                    display: false
                }
            }
        }
    });
}

function updateDashboard() {
    // In a real app, this would fetch data from an API
    const data = {
        totalInventory: 1245,
        goodCondition: 1120,
        nearExpiry: 85,
        spoiled: 40
    };
    
    document.getElementById('total-inventory').textContent = data.totalInventory.toLocaleString();
    document.getElementById('good-condition').textContent = data.goodCondition.toLocaleString();
    document.getElementById('near-expiry').textContent = data.nearExpiry.toLocaleString();
    document.getElementById('spoiled').textContent = data.spoiled.toLocaleString();
}

function saveInventory() {
    const form = document.getElementById('inventoryForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    // In a real app, this would send data to a server
    const formData = {
        meatType: document.getElementById('meatType').value,
        cutType: document.getElementById('cutType').value,
        quantity: document.getElementById('quantity').value,
        processingDate: document.getElementById('processingDate').value,
        expiryDate: document.getElementById('expiryDate').value,
        batchNumber: document.getElementById('batchNumber').value,
        storageLocation: document.getElementById('storageLocation').value,
        qualityNotes: document.getElementById('qualityNotes').value
    };
    
    console.log('Saving inventory:', formData);
    
    // Show success message
    alert('Inventory record saved successfully!');
    
    // Close modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('inventoryModal'));
    modal.hide();
    
    // Reset form
    form.reset();
    
    // Update dashboard
    updateDashboard();
}

// Simulate real-time monitoring updates
setInterval(() => {
    // In a real app, this would fetch updated sensor data
    const randomTemp = (Math.random() * 2 + 2).toFixed(1);
    const randomHumidity = (Math.random() * 10 + 70).toFixed(1);
    
    // Randomly generate alerts
    if (Math.random() > 0.7) {
        const alertContainer = document.querySelector('.card-body');
        const alert = document.createElement('div');
        alert.className = 'alert alert-warning alert-dismissible fade show';
        alert.role = 'alert';
        alert.innerHTML = `
            <strong>Temperature Alert!</strong> Storage Unit ${Math.floor(Math.random() * 4) + 1} 
            temperature is ${randomTemp}°C (threshold: 4°C)
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        alertContainer.prepend(alert);
    }
}, 30000);