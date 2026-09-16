
import { useEffect, useState } from "react";

type ApiHealth = {
  status: string;
  message: string;
};

export default function ApiStatus() {
  const [message, setMessage] = useState("Verbinding controleren…");

  useEffect(() => {
    const controller = new AbortController();

    async function checkApi() {
      try {
        const response = await fetch("/api/health", {
          signal: controller.signal,
        });

        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }

        const data: ApiHealth = await response.json();

        if (data.status !== "ok") {
          throw new Error("API meldt geen OK-status");
        }

        setMessage(data.message);
      } catch (error) {
        if (!controller.signal.aborted) {
          setMessage(
            error instanceof Error
              ? `API niet bereikbaar: ${error.message}`
              : "API niet bereikbaar",
          );
        }
      }
    }

    void checkApi();

    return () => controller.abort();
  }, []);

  return (
    <div className="cms-info-panel" role="status">
      <h2>Backendverbinding</h2>
      <p>{message}</p>
    </div>
  );
}