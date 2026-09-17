import { useState } from "react";
import type { FormEvent } from "react";

const EMAIL = "r.g.b.janssen@st.hanze.nl";
const PHONE = "+31644394350";
const WHATSAPP = "https://wa.me/31644394350";

export default function Contact() {
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [subject, setSubject] = useState("");
  const [message, setMessage] = useState("");
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [statusMessage, setStatusMessage] = useState("");
  const [errorMessage, setErrorMessage] = useState("");

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    setIsSubmitting(true);
    setStatusMessage("");
    setErrorMessage("");

    try {
      const response = await fetch("/api/contact", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          name,
          email,
          subject,
          message,
          website: "",
        }),
      });

      const result = (await response.json()) as {
        message?: string;
        error?: string;
      };

      if (!response.ok) {
        throw new Error(
          result.error || "Je bericht kon niet worden verzonden.",
        );
      }

      setName("");
      setEmail("");
      setSubject("");
      setMessage("");
      setStatusMessage(result.message || "Bedankt, je bericht is verzonden.");
    } catch (error) {
      setErrorMessage(
        error instanceof Error
          ? error.message
          : "Je bericht kon niet worden verzonden. Probeer het later opnieuw.",
      );
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <section id="contact" className="rgb-contact">
      <div className="rgb-contact__container">
        <div className="rgb-contact__heading">
          <p className="hero-label">CONTACT</p>

          <h2>
            Een idee in gedachten?
            <br />
            <span>Laten we het bouwen.</span>
          </h2>

          <p>
            Een nieuwe website, een redesign of gewoon eens kennismaken? Vertel
            me waar je aan werkt. Ik denk graag met je mee over de
            mogelijkheden.
          </p>
        </div>

        <div className="rgb-contact__layout">
          <div className="rgb-contact__info">
            <p className="rgb-contact__eyebrow">DIRECT CONTACT</p>

            <h3>Je kunt me ook direct bereiken.</h3>

            <p className="rgb-contact__info-text">
              Kies de manier die voor jou het prettigst werkt.
            </p>

            <a className="rgb-contact__method" href={`mailto:${EMAIL}`}>
              <span className="rgb-contact__method-icon" aria-hidden="true">
                @
              </span>

              <span className="rgb-contact__method-content">
                <small>E-MAIL</small>
                <strong>{EMAIL}</strong>
              </span>

              <span aria-hidden="true">↗</span>
            </a>

            <a
              className="rgb-contact__method"
              href={`${WHATSAPP}?text=${encodeURIComponent(
                "Hoi Ruben, ik neem contact met je op via RGB Visuals.",
              )}`}
              target="_blank"
              rel="noopener noreferrer"
            >
              <span className="rgb-contact__method-icon" aria-hidden="true">
                ↗
              </span>

              <span className="rgb-contact__method-content">
                <small>WHATSAPP</small>
                <strong>Stuur me een bericht</strong>
              </span>

              <span aria-hidden="true">↗</span>
            </a>

            <a className="rgb-contact__method" href={`tel:${PHONE}`}>
              <span className="rgb-contact__method-icon" aria-hidden="true">
                ☎
              </span>

              <span className="rgb-contact__method-content">
                <small>TELEFOON</small>
                <strong>06 44 39 43 50</strong>
              </span>

              <span aria-hidden="true">↗</span>
            </a>

            <div className="rgb-contact__note">
              <span className="rgb-contact__note-dot" />
              Beschikbaar voor nieuwe samenwerkingen
            </div>
          </div>

          <div className="rgb-contact__form-card">
            <div className="rgb-contact__form-heading">
              <span className="rgb-contact__eyebrow">STUUR EEN BERICHT</span>

              <h3>Vertel me over je project.</h3>

              <p>
                Vul het formulier in en ik neem zo snel mogelijk contact met je
                op.
              </p>
            </div>

            <form className="rgb-contact__form" onSubmit={handleSubmit}>
              <div className="rgb-contact__field-row">
                <div className="rgb-contact__field">
                  <label htmlFor="contact-name">
                    Naam <span>*</span>
                  </label>

                  <input
                    id="contact-name"
                    name="name"
                    type="text"
                    autoComplete="name"
                    placeholder="Jouw naam"
                    value={name}
                    onChange={(event) => setName(event.target.value)}
                    maxLength={100}
                    required
                  />
                </div>

                <div className="rgb-contact__field">
                  <label htmlFor="contact-email">
                    E-mailadres <span>*</span>
                  </label>

                  <input
                    id="contact-email"
                    name="email"
                    type="email"
                    autoComplete="email"
                    placeholder="naam@bedrijf.nl"
                    value={email}
                    onChange={(event) => setEmail(event.target.value)}
                    maxLength={190}
                    required
                  />
                </div>
              </div>

              <div className="rgb-contact__field">
                <label htmlFor="contact-subject">Onderwerp</label>

                <input
                  id="contact-subject"
                  name="subject"
                  type="text"
                  placeholder="Bijvoorbeeld: Nieuwe website"
                  value={subject}
                  onChange={(event) => setSubject(event.target.value)}
                  maxLength={150}
                />
              </div>

              <div className="rgb-contact__field">
                <label htmlFor="contact-message">
                  Jouw bericht <span>*</span>
                </label>

                <textarea
                  id="contact-message"
                  name="message"
                  rows={6}
                  placeholder="Vertel me meer over je idee of project..."
                  value={message}
                  onChange={(event) => setMessage(event.target.value)}
                  maxLength={3000}
                  required
                />
              </div>

              <div className="rgb-contact__honeypot" aria-hidden="true">
                <label htmlFor="contact-website">Website</label>
                <input
                  id="contact-website"
                  name="website"
                  type="text"
                  tabIndex={-1}
                  autoComplete="off"
                  value=""
                  onChange={() => undefined}
                />
              </div>

              <button
                className="rgb-contact__submit"
                type="submit"
                disabled={isSubmitting}
              >
                <span>
                  {isSubmitting
                    ? "Bericht wordt verzonden..."
                    : "Verstuur bericht"}
                </span>
                <span aria-hidden="true">↗</span>
              </button>

              {statusMessage && (
                <p className="rgb-contact__form-status" role="status">
                  {statusMessage}
                </p>
              )}

              {errorMessage && (
                <p
                  className="rgb-contact__form-status rgb-contact__form-status--error"
                  role="alert"
                >
                  {errorMessage}
                </p>
              )}

              <p className="rgb-contact__form-disclaimer">
                Je bericht wordt veilig rechtstreeks naar RGB Visuals verstuurd.
              </p>
            </form>
          </div>
        </div>
      </div>
    </section>
  );
}
