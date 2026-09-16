
import { useEffect, useState } from "react";
import type { FormEvent } from "react";

import AdminSidebar from "./components/AdminSidebar";
import type { AdminPage } from "./components/AdminSidebar";

import AdminOverview from "./pages/AdminOverview";
import ProjectEditor from "./pages/ProjectEditor";

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

const pageTitles: Record<AdminPage, string> = {
  overview: "Overzicht",
  projects: "Projecten",
  clients: "Klanten",
  calendar: "Agenda",
  finances: "Financiën",
  settings: "Instellingen",
};

export default function AdminDashboard() {
  const [activePage, setActivePage] = useState<AdminPage>("overview");

  const [authStatus, setAuthStatus] = useState<
    "checking" | "logged-out" | "logged-in" | "error"
  >("checking");

  const [user, setUser] = useState<AdminUser | null>(null);
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [message, setMessage] = useState("");
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    let cancelled = false;

    async function checkSession() {
      try {
        const response = await fetch("/api/auth/me", {
          credentials: "same-origin",
          cache: "no-store",
        });

        if (cancelled) return;

        if (response.status === 401) {
          setAuthStatus("logged-out");
          return;
        }

        if (!response.ok) {
          throw new Error("Sessiecontrole mislukt");
        }

        const data: AuthResponse = await response.json();

        if (cancelled) return;

        if (data.authenticated && data.user) {
          setUser(data.user);
          setAuthStatus("logged-in");
        } else {
          setAuthStatus("logged-out");
        }
      } catch {
        if (!cancelled) {
          setAuthStatus("error");
          setMessage("De verbinding met de server is mislukt.");
        }
      }
    }

    void checkSession();

    return () => {
      cancelled = true;
    };
  }, []);

  async function handleLogin(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (isSubmitting) return;

    setIsSubmitting(true);
    setMessage("");

    try {
      const response = await fetch("/api/auth/login", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        credentials: "same-origin",
        body: JSON.stringify({ email, password }),
      });

      const data: AuthResponse = await response.json();

      if (!response.ok || !data.authenticated || !data.user) {
        setMessage(data.error ?? "Inloggen is mislukt.");
        return;
      }

      setUser(data.user);
      setPassword("");
      setAuthStatus("logged-in");
    } catch {
      setMessage("Kan geen verbinding maken met de server.");
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleLogout() {
    if (isSubmitting) return;

    setIsSubmitting(true);
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
      setEmail("");
      setPassword("");
      setActivePage("overview");
      setAuthStatus("logged-out");
    } catch {
      setMessage("Uitloggen is niet gelukt. Probeer het opnieuw.");
    } finally {
      setIsSubmitting(false);
    }
  }

  if (authStatus === "checking") {
    return (
      <main className="cms-placeholder">
        <p className="hero-label">RGB VISUALS CMS</p>
        <h1>Sessie controleren...</h1>
      </main>
    );
  }

  if (authStatus === "error") {
    return (
      <main className="cms-placeholder">
        <p className="hero-label">RGB VISUALS CMS</p>
        <h1>Verbinding mislukt</h1>
        <p role="alert">{message}</p>
        <button type="button" onClick={() => window.location.reload()}>
          Opnieuw proberen
        </button>
      </main>
    );
  }

  if (authStatus === "logged-out") {
    return (
      <main className="cms-placeholder">
        <p className="hero-label">RGB VISUALS CMS</p>
        <h1>Inloggen</h1>
        <p>Log in om je dashboard te openen.</p>

        <form
          onSubmit={handleLogin}
          style={{
            display: "grid",
            gap: "1rem",
            maxWidth: "420px",
            marginTop: "1.5rem",
          }}
        >
          <label style={{ display: "grid", gap: "0.4rem" }}>
            E-mailadres
            <input
              type="email"
              autoComplete="username"
              value={email}
              onChange={(event) => setEmail(event.target.value)}
              required
            />
          </label>

          <label style={{ display: "grid", gap: "0.4rem" }}>
            Wachtwoord
            <input
              type="password"
              autoComplete="current-password"
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              required
            />
          </label>

          {message && <p role="alert">{message}</p>}

          <button type="submit" disabled={isSubmitting}>
            {isSubmitting ? "Bezig met inloggen..." : "Inloggen"}
          </button>
        </form>
      </main>
    );
  }

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
            disabled={isSubmitting}
          >
            Uitloggen
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