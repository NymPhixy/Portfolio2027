import { useEffect, useState } from "react";

import AdminSidebar from "./components/AdminSidebar";
import type { AdminPage } from "./components/AdminSidebar";

import AdminOverview from "./pages/AdminOverview";
import ProjectEditor from "./pages/ProjectEditor";
import StudioLogin from "./components/StudioLogin";

type AdminUser = {
  id: number;
  name: string;
  email: string;
};

type AuthResponse = {
  authenticated: boolean;
  user?: AdminUser;
  error?: string;
};

type AuthStatus = "checking" | "logged-out" | "logged-in" | "error";

const pageTitles: Record<AdminPage, string> = {
  overview: "Overzicht",
  projects: "Projecten",
  clients: "Klanten",
  calendar: "Agenda",
  finances: "Financiën",
  settings: "Instellingen",
};

/**
 * Vraagt de huidige PHP-sessie op.
 * Geeft null terug als er geen ingelogde gebruiker is.
 */
async function requestSession(
  signal?: AbortSignal,
): Promise<AuthResponse | null> {
  const response = await fetch("/api/auth/me", {
    credentials: "same-origin",
    cache: "no-store",
    signal,
  });

  if (response.status === 401) {
    return null;
  }

  if (!response.ok) {
    throw new Error("Sessiecontrole mislukt");
  }

  const data: AuthResponse = await response.json();

  return data;
}

export default function AdminDashboard() {
  const [activePage, setActivePage] = useState<AdminPage>("overview");

  const [authStatus, setAuthStatus] = useState<AuthStatus>("checking");

  const [user, setUser] = useState<AdminUser | null>(null);
  const [message, setMessage] = useState("");
  const [isLoggingOut, setIsLoggingOut] = useState(false);

  /**
   * Controleer de sessie wanneer het CMS voor het eerst opent.
   */
  useEffect(() => {
    const controller = new AbortController();

    async function loadInitialSession() {
      try {
        const data = await requestSession(controller.signal);

        if (controller.signal.aborted) {
          return;
        }

        if (data?.authenticated && data.user) {
          setUser(data.user);
          setAuthStatus("logged-in");
        } else {
          setUser(null);
          setAuthStatus("logged-out");
        }
      } catch {
        if (controller.signal.aborted) {
          return;
        }

        setUser(null);
        setMessage("De verbinding met de server is mislukt.");
        setAuthStatus("error");
      }
    }

    void loadInitialSession();

    return () => {
      controller.abort();
    };
  }, []);

  /**
   * Controleer de sessie opnieuw na inloggen
   * of na een klik op 'Opnieuw proberen'.
   */
  async function verifySession() {
    try {
      const data = await requestSession();

      if (data?.authenticated && data.user) {
        setUser(data.user);
        setAuthStatus("logged-in");
      } else {
        setUser(null);
        setAuthStatus("logged-out");
      }
    } catch {
      setUser(null);
      setMessage("De verbinding met de server is mislukt.");
      setAuthStatus("error");
    }
  }

  /**
   * StudioLogin heeft de loginrequest uitgevoerd.
   * Haal vervolgens de ingelogde gebruiker op.
   */
  function handleAuthenticated() {
    setAuthStatus("checking");
    setMessage("");

    void verifySession();
  }

  /**
   * Log uit via de bestaande PHP-route.
   */
  async function handleLogout() {
    if (isLoggingOut) {
      return;
    }

    setIsLoggingOut(true);
    setMessage("");

    try {
      const response = await fetch("/api/auth/logout", {
        method: "POST",
        credentials: "same-origin",
      });

      if (!response.ok) {
        throw new Error("Uitloggen mislukt");
      }

      setUser(null);
      setActivePage("overview");
      setAuthStatus("logged-out");
    } catch {
      setMessage("Uitloggen is niet gelukt. Probeer het opnieuw.");
    } finally {
      setIsLoggingOut(false);
    }
  }

  /**
   * Sessie wordt gecontroleerd.
   */
  if (authStatus === "checking") {
    return (
      <main className="studio-login">
        <div className="studio-login__panel">
          <div className="studio-login__card">
            <p className="studio-login__eyebrow">RGB VISUALS CMS</p>

            <h2>Een momentje.</h2>

            <p className="studio-login__subtitle" role="status">
              Je studiosessie wordt gecontroleerd...
            </p>
          </div>
        </div>
      </main>
    );
  }

  /**
   * De API is niet bereikbaar of de sessiecontrole is mislukt.
   */
  if (authStatus === "error") {
    return (
      <main className="studio-login">
        <div className="studio-login__panel">
          <div className="studio-login__card">
            <p className="studio-login__eyebrow">RGB VISUALS CMS</p>

            <h2>Verbinding mislukt.</h2>

            <p className="studio-login__error" role="alert">
              {message}
            </p>

            <button
              type="button"
              className="studio-login__submit"
              onClick={handleAuthenticated}
            >
              <span>Opnieuw proberen</span>
              <span aria-hidden="true">↗</span>
            </button>
          </div>
        </div>
      </main>
    );
  }

  /**
   * Nieuwe vormgegeven inlogomgeving.
   */
  if (authStatus === "logged-out") {
    return <StudioLogin onAuthenticated={handleAuthenticated} />;
  }

  /**
   * Bestaande CMS-omgeving.
   */
  return (
    <div className="cms-layout">
      <AdminSidebar activePage={activePage} onNavigate={setActivePage} />

      <main className="cms-main">
        <div
          style={{
            display: "flex",
            justifyContent: "flex-end",
            alignItems: "center",
            gap: "1rem",
            marginBottom: "1rem",
          }}
        >
          <span>Ingelogd als {user?.name}</span>

          <button
            type="button"
            onClick={() => void handleLogout()}
            disabled={isLoggingOut}
          >
            {isLoggingOut ? "Uitloggen..." : "Uitloggen"}
          </button>
        </div>

        {message && <p role="alert">{message}</p>}

        {activePage === "overview" && (
          <AdminOverview onNavigate={setActivePage} />
        )}

        {activePage === "projects" && <ProjectEditor />}

        {activePage !== "overview" && activePage !== "projects" && (
          <div className="cms-placeholder">
            <p className="hero-label">RGB VISUALS CMS</p>

            <h1>{pageTitles[activePage]}</h1>

            <p>
              Deze module krijgt in een volgende bouwfase zijn eigen
              functionaliteit.
            </p>
          </div>
        )}
      </main>
    </div>
  );
}
