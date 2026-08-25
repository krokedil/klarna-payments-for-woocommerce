import { defineConfig } from "allure";

export default defineConfig({
  name: "Klarna Payments tests",
  plugins: {
    awesome: {
      options: {
        reportName: "Klarna Payments tests",
      },
    },
  },
});
