/**
 * Chart.js Helper Wrappers for ExamPortal
 */
const Charts = {
  instances: {},

  destroy(canvasId) {
    if (this.instances[canvasId]) {
      this.instances[canvasId].destroy();
      delete this.instances[canvasId];
    }
  },

  line(canvasId, labels, datasets, options = {}) {
    this.destroy(canvasId);
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;

    const formattedDatasets = datasets.map((ds, idx) => ({
      label: ds.label || `Dataset ${idx + 1}`,
      data: ds.data || [],
      borderColor: ds.color || '#4F46E5',
      backgroundColor: ds.fillColor || 'rgba(79, 70, 229, 0.1)',
      borderWidth: 2.5,
      tension: 0.35,
      fill: ds.fill !== undefined ? ds.fill : true,
      pointRadius: 4,
      pointHoverRadius: 6,
    }));

    this.instances[canvasId] = new Chart(ctx, {
      type: 'line',
      data: { labels, datasets: formattedDatasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: datasets.length > 1, position: 'top' },
          tooltip: {
            backgroundColor: '#1E1B4B',
            padding: 10,
            cornerRadius: 8,
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            max: options.max || 100,
            grid: { color: 'rgba(0,0,0,0.05)' }
          },
          x: {
            grid: { display: false }
          }
        },
        ...options.customOptions
      }
    });
    return this.instances[canvasId];
  },

  bar(canvasId, labels, datasets, options = {}) {
    this.destroy(canvasId);
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;

    const formattedDatasets = datasets.map((ds, idx) => ({
      label: ds.label || `Dataset ${idx + 1}`,
      data: ds.data || [],
      backgroundColor: ds.color || '#4F46E5',
      borderRadius: 6,
      maxBarThickness: 40,
    }));

    this.instances[canvasId] = new Chart(ctx, {
      type: 'bar',
      data: { labels, datasets: formattedDatasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: datasets.length > 1 },
          tooltip: { backgroundColor: '#1E1B4B', padding: 10, cornerRadius: 8 }
        },
        scales: {
          y: { beginAtZero: true, max: options.max || undefined, grid: { color: 'rgba(0,0,0,0.05)' } },
          x: { grid: { display: false } }
        },
        ...options.customOptions
      }
    });
    return this.instances[canvasId];
  },

  doughnut(canvasId, labels, data, colors, options = {}) {
    this.destroy(canvasId);
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;

    this.instances[canvasId] = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels,
        datasets: [{
          data,
          backgroundColor: colors || ['#059669', '#DC2626', '#9CA3AF'],
          borderWidth: 0,
          hoverOffset: 4
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '70%',
        plugins: {
          legend: { position: 'bottom', labels: { boxWidth: 12, padding: 15 } },
          tooltip: { backgroundColor: '#1E1B4B', padding: 10, cornerRadius: 8 }
        },
        ...options.customOptions
      }
    });
    return this.instances[canvasId];
  },

  radar(canvasId, labels, datasets, options = {}) {
    this.destroy(canvasId);
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;

    const formattedDatasets = datasets.map(ds => ({
      label: ds.label,
      data: ds.data,
      borderColor: ds.color || '#4F46E5',
      backgroundColor: ds.fillColor || 'rgba(79, 70, 229, 0.2)',
      pointBackgroundColor: ds.color || '#4F46E5',
      borderWidth: 2,
    }));

    this.instances[canvasId] = new Chart(ctx, {
      type: 'radar',
      data: { labels, datasets: formattedDatasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          r: {
            angleLines: { color: 'rgba(0,0,0,0.08)' },
            grid: { color: 'rgba(0,0,0,0.05)' },
            suggestedMin: 0,
            suggestedMax: 100
          }
        },
        ...options.customOptions
      }
    });
    return this.instances[canvasId];
  }
};
