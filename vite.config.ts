import react, { reactCompilerPreset } from "@vitejs/plugin-react";
import babel from "@rolldown/plugin-babel";
import { defineConfig } from "vite";

export default defineConfig({
  plugins: [react(), babel({ presets: [reactCompilerPreset()] })],

  server: {
    proxy: {
      "/api": {
        target: process.env.DOCKER_API_URL || "http://127.0.0.1:8081",
        changeOrigin: true,
      },
    },
  },
});
