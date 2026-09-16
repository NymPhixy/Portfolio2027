import { useId, useState } from "react";
import type { FormEvent } from "react";

type StudioLoginProps = {
  onAuthenticated: () => void;
};

type LoginResponse = {
  authenticated?: boolean;
  error?: string;
};

export default function StudioLogin({ onAuthenticated }: StudioLoginProps) {
  const emailId = useId();
  const passwordId = useId();

  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState("");

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (isLoading) return;

    setError("");
    setIsLoading(true);

    try {
      const response = await fetch("/api/auth/login", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        credentials: "same-origin",
        body: JSON.stringify({
          email: email.trim(),
          password,
        }),
      });

      const data: LoginResponse = await response.json();

      if (!response.ok || data.authenticated !== true) {
        throw new Error(data.error ?? "Inloggen is mislukt. Probeer opnieuw.");
      }

      setPassword("");
      onAuthenticated();
    } catch (loginError) {
      setError(
        loginError instanceof Error
          ? loginError.message
          : "Er is iets misgegaan. Probeer opnieuw.",
      );
    } finally {
      setIsLoading(false);
    }
  }

  return (
    <main className="studio-login">
      <div className="studio-login__glow studio-login__glow--pink" />
      <div className="studio-login__glow studio-login__glow--cyan" />

      <div className="studio-login__layout">
        <section className="studio-login__intro">
          <a className="studio-login__brand" href="/">
            <span className="studio-login__brand-mark">RGB</span>

            <span className="studio-login__brand-text">
              <strong>RGB Visuals</strong>
              <small>Creative digital studio</small>
            </span>
          </a>

          <div className="studio-login__intro-content">
            <span className="studio-login__eyebrow">
              YOUR CREATIVE WORKSPACE
            </span>

            <h1>
              Create.
              <br />
              Manage.
              <br />
              <span>Inspire.</span>
            </h1>

            <p>
              Eén plek voor je projecten, casestudy&apos;s en creatieve ideeën.
            </p>

            <div className="studio-login__decor" aria-hidden="true">
              <span />
              <span />
              <span />
            </div>
          </div>

          <span className="studio-login__intro-footer">
            © {new Date().getFullYear()} RGB Visuals
          </span>
        </section>

        <section className="studio-login__panel">
          <div className="studio-login__card">
            <div className="studio-login__card-top">
              <span className="studio-login__icon" aria-hidden="true">
                ✦
              </span>

              <span className="studio-login__access-label">STUDIO ACCESS</span>
            </div>

            <h2>Welkom terug.</h2>

            <p className="studio-login__subtitle">
              Log in om verder te werken aan je portfolio.
            </p>

            <form
              className="studio-login__form"
              onSubmit={(event) => void handleSubmit(event)}
            >
              <div className="studio-login__field">
                <label htmlFor={emailId}>E-mailadres</label>

                <input
                  id={emailId}
                  type="email"
                  name="email"
                  autoComplete="username"
                  placeholder="naam@voorbeeld.nl"
                  value={email}
                  onChange={(event) => setEmail(event.target.value)}
                  disabled={isLoading}
                  required
                />
              </div>

              <div className="studio-login__field">
                <label htmlFor={passwordId}>Wachtwoord</label>

                <div className="studio-login__password">
                  <input
                    id={passwordId}
                    type={showPassword ? "text" : "password"}
                    name="password"
                    autoComplete="current-password"
                    placeholder="Voer je wachtwoord in"
                    value={password}
                    onChange={(event) => setPassword(event.target.value)}
                    disabled={isLoading}
                    required
                  />

                  <button
                    type="button"
                    className="studio-login__password-toggle"
                    onClick={() => setShowPassword((current) => !current)}
                    disabled={isLoading}
                    aria-label={
                      showPassword ? "Verberg wachtwoord" : "Toon wachtwoord"
                    }
                    aria-pressed={showPassword}
                  >
                    {showPassword ? "Verberg" : "Toon"}
                  </button>
                </div>
              </div>

              {error && (
                <p className="studio-login__error" role="alert">
                  {error}
                </p>
              )}

              <button
                className="studio-login__submit"
                type="submit"
                disabled={isLoading}
              >
                <span>
                  {isLoading ? "Even geduld..." : "Inloggen bij je studio"}
                </span>

                <span aria-hidden="true">↗</span>
              </button>
            </form>

            <div className="studio-login__card-footer">
              <span className="studio-login__status-dot" />

              <span>Persoonlijke CMS-omgeving</span>
            </div>
          </div>

          <a className="studio-login__back" href="/">
            ← Terug naar portfolio
          </a>
        </section>
      </div>
    </main>
  );
}
