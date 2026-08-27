/** PM2 — útil en VPS BlueHosting / SSH */
module.exports = {
  apps: [
    {
      name: "inventario",
      script: "start.sh",
      interpreter: "bash",
      cwd: __dirname,
      env: {
        NODE_ENV: "production",
        PORT: 3000,
        HOSTNAME: "0.0.0.0",
      },
      max_memory_restart: "512M",
      time: true,
    },
  ],
};
