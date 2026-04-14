(function () {
    const data = window.reportChartData || {};

    const monthlyLabels = data.monthlyLabels || [];
    const monthlyIncome = data.monthlyIncome || [];
    const monthlyExpenses = data.monthlyExpenses || [];
    const monthlyNet = data.monthlyNet || [];
    const expenseLabels = data.expenseLabels || [];
    const expenseValues = data.expenseValues || [];
    const classIncomeLabels = data.classIncomeLabels || [];
    const classIncomeValues = data.classIncomeValues || [];
    const classBalanceLabels = data.classBalanceLabels || [];
    const classBalanceValues = data.classBalanceValues || [];
    const classBalanceCollection = data.classBalanceCollection || [];
    const forecastLabels = data.forecastLabels || [];
    const forecastValues = data.forecastValues || [];
    const forecastActualCount = Number(data.forecastActualCount || 0);
    const budgetActual = data.budgetActual || [0, 0];
    const budgetPlan = data.budgetPlan || [0, 0];
    const budgetLabels = ['Income', 'Expenses'];

    const chartPalette = [
        '#6b4cf6', '#22c55e', '#f59e0b', '#ef4444', '#3b82f6', '#14b8a6',
        '#8b5cf6', '#06b6d4', '#84cc16', '#f97316', '#ec4899', '#64748b'
    ];

    const formatCurrency = function (value, minimumFractionDigits) {
        const minDigits = typeof minimumFractionDigits === 'number' ? minimumFractionDigits : 2;
        return 'GH₵' + Number(value || 0).toLocaleString(undefined, {
            minimumFractionDigits: minDigits,
            maximumFractionDigits: 2,
        });
    };

    const formatAxisCurrency = function (value) {
        return 'GH₵' + Number(value || 0).toLocaleString();
    };

    const trendCanvas = document.getElementById('trendChart');
    if (trendCanvas && window.Chart) {
        new Chart(trendCanvas, {
            type: 'line',
            data: {
                labels: monthlyLabels,
                datasets: [
                    {
                        label: 'Income',
                        data: monthlyIncome,
                        borderColor: '#54a33d',
                        backgroundColor: 'rgba(84, 163, 61, 0.14)',
                        tension: 0.38,
                        fill: true,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                    },
                    {
                        label: 'Expenses',
                        data: monthlyExpenses,
                        borderColor: '#d64c4c',
                        backgroundColor: 'rgba(214, 76, 76, 0.12)',
                        tension: 0.38,
                        fill: true,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                    },
                    {
                        label: 'Net',
                        data: monthlyNet,
                        borderColor: '#6b4cf6',
                        backgroundColor: 'rgba(107, 76, 246, 0.10)',
                        tension: 0.38,
                        fill: false,
                        borderDash: [6, 6],
                        pointRadius: 2,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            boxWidth: 8,
                            color: '#475569'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return context.dataset.label + ': ' + formatCurrency(context.raw);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: '#64748b' }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#edf2f7' },
                        ticks: {
                            color: '#64748b',
                            callback: function (value) {
                                return formatAxisCurrency(value);
                            }
                        }
                    }
                }
            }
        });
    }

    const expenseCanvas = document.getElementById('expenseChart');
    if (expenseCanvas && window.Chart) {
        new Chart(expenseCanvas, {
            type: 'doughnut',
            data: {
                labels: expenseLabels.length ? expenseLabels : ['No data'],
                datasets: [{
                    data: expenseValues.length ? expenseValues : [1],
                    backgroundColor: chartPalette.slice(0, Math.max(expenseValues.length, 1)),
                    borderWidth: 0,
                    hoverOffset: 8,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            color: '#475569'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return context.label + ': ' + formatCurrency(context.raw);
                            }
                        }
                    }
                }
            }
        });
    }

    const budgetCanvas = document.getElementById('budgetChart');
    if (budgetCanvas && window.Chart) {
        new Chart(budgetCanvas, {
            type: 'bar',
            data: {
                labels: budgetLabels,
                datasets: [
                    {
                        label: 'Budget',
                        data: budgetPlan,
                        backgroundColor: 'rgba(107, 76, 246, 0.35)',
                        borderColor: '#6b4cf6',
                        borderWidth: 1,
                        borderRadius: 8,
                    },
                    {
                        label: 'Actual',
                        data: budgetActual,
                        backgroundColor: ['rgba(84, 163, 61, 0.55)', 'rgba(214, 76, 76, 0.55)'],
                        borderColor: ['#54a33d', '#d64c4c'],
                        borderWidth: 1,
                        borderRadius: 8,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            color: '#475569'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return context.dataset.label + ': ' + formatCurrency(context.raw);
                            }
                        }
                    }
                },
                scales: {
                    x: { ticks: { color: '#64748b' }, grid: { display: false } },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: '#64748b',
                            callback: function (value) {
                                return formatAxisCurrency(value);
                            }
                        },
                        grid: { color: '#edf2f7' }
                    }
                }
            }
        });
    }

    const classIncomeCanvas = document.getElementById('classIncomeChart');
    if (classIncomeCanvas && window.Chart) {
        new Chart(classIncomeCanvas, {
            type: 'bar',
            data: {
                labels: classIncomeLabels.length ? classIncomeLabels : ['No data'],
                datasets: [{
                    label: 'Income by Class',
                    data: classIncomeValues.length ? classIncomeValues : [0],
                    backgroundColor: 'rgba(107, 76, 246, 0.55)',
                    borderColor: '#6b4cf6',
                    borderWidth: 1,
                    borderRadius: 8,
                    barThickness: 16,
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return formatCurrency(context.raw);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: { color: '#edf2f7' },
                        ticks: {
                            color: '#64748b',
                            callback: function (value) {
                                return formatAxisCurrency(value);
                            }
                        }
                    },
                    y: {
                        grid: { display: false },
                        ticks: { color: '#64748b' }
                    }
                }
            }
        });
    }

    const forecastCanvas = document.getElementById('forecastChart');
    if (forecastCanvas && window.Chart) {
        new Chart(forecastCanvas, {
            type: 'line',
            data: {
                labels: forecastLabels,
                datasets: [
                    {
                        label: 'Actual Net',
                        data: forecastValues.map(function (value, index) {
                            return index < forecastActualCount ? value : null;
                        }),
                        borderColor: '#1f8a70',
                        backgroundColor: 'rgba(31, 138, 112, 0.12)',
                        tension: 0.35,
                        fill: true,
                        pointRadius: 3,
                        spanGaps: false,
                    },
                    {
                        label: 'Forecast',
                        data: forecastValues.map(function (value, index) {
                            return index >= forecastActualCount - 1 ? value : null;
                        }),
                        borderColor: '#f59e0b',
                        borderDash: [6, 6],
                        backgroundColor: 'rgba(245, 158, 11, 0.12)',
                        tension: 0.35,
                        fill: false,
                        pointRadius: 3,
                        spanGaps: true,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { usePointStyle: true, color: '#475569' }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return context.dataset.label + ': ' + formatCurrency(context.raw);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: '#64748b' }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#edf2f7' },
                        ticks: {
                            color: '#64748b',
                            callback: function (value) {
                                return formatAxisCurrency(value);
                            }
                        }
                    }
                }
            }
        });
    }

    const classBalanceCanvas = document.getElementById('classBalanceChart');
    if (classBalanceCanvas && window.Chart) {
        new Chart(classBalanceCanvas, {
            type: 'bar',
            data: {
                labels: classBalanceLabels.length ? classBalanceLabels : ['No data'],
                datasets: [{
                    label: 'Outstanding Balance',
                    data: classBalanceValues.length ? classBalanceValues : [0],
                    backgroundColor: 'rgba(214, 76, 76, 0.58)',
                    borderColor: '#d64c4c',
                    borderWidth: 1,
                    borderRadius: 8,
                    barThickness: 16,
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return formatCurrency(context.raw);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: { color: '#edf2f7' },
                        ticks: {
                            color: '#64748b',
                            callback: function (value) {
                                return formatAxisCurrency(value);
                            }
                        }
                    },
                    y: {
                        grid: { display: false },
                        ticks: { color: '#64748b' }
                    }
                }
            }
        });
    }

    const classBalanceRateCanvas = document.getElementById('classBalanceRateChart');
    if (classBalanceRateCanvas && window.Chart) {
        new Chart(classBalanceRateCanvas, {
            type: 'radar',
            data: {
                labels: classBalanceLabels.length ? classBalanceLabels : ['No data'],
                datasets: [{
                    label: 'Collection Rate %',
                    data: classBalanceCollection.length ? classBalanceCollection : [0],
                    borderColor: '#3d7ee6',
                    backgroundColor: 'rgba(61, 126, 230, 0.15)',
                    pointBackgroundColor: '#3d7ee6',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: '#3d7ee6',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { usePointStyle: true, color: '#475569' }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return Number(context.raw || 0).toFixed(1) + '% collected';
                            }
                        }
                    }
                },
                scales: {
                    r: {
                        beginAtZero: true,
                        angleLines: { color: '#edf2f7' },
                        grid: { color: '#edf2f7' },
                        pointLabels: { color: '#64748b' },
                        ticks: {
                            color: '#94a3b8',
                            backdropColor: 'transparent',
                            callback: function (value) {
                                return value + '%';
                            }
                        }
                    }
                }
            }
        });
    }
})();
