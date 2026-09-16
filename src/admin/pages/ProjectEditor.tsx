import { useEffect, useState } from "react";
import type { FormEvent } from "react";

type ProjectForm = {
  title: string;
  description: string;
  client: string;
  year: string;
  status: "draft" | "published";
};

type Project = {
  id: number;
  title: string;
  slug: string;
  summary: string | null;
  description: string | null;
  client: string | null;
  year: number | null;
  cover_image: string | null;
  status: "draft" | "published";
  created_at: string;
  updated_at: string;
};

type ProjectsResponse = {
  projects: Project[];
  error?: string;
};

type SaveResponse = {
  message?: string;
  error?: string;
};

function createEmptyForm(): ProjectForm {
  return {
    title: "",
    description: "",
    client: "",
    year: String(new Date().getFullYear()),
    status: "draft",
  };
}

export default function ProjectEditor() {
  const [form, setForm] = useState<ProjectForm>(createEmptyForm);
  const [editingId, setEditingId] = useState<number | null>(null);

  const [projects, setProjects] = useState<Project[]>([]);
  const [projectsLoading, setProjectsLoading] = useState(true);
  const [projectsError, setProjectsError] = useState<string | null>(null);

  const [isSaving, setIsSaving] = useState(false);
  const [saveMessage, setSaveMessage] = useState("");
  const [saveError, setSaveError] = useState("");

  const [refreshKey, setRefreshKey] = useState(0);

  useEffect(() => {
    const controller = new AbortController();

    async function loadProjects() {
      setProjectsLoading(true);
      setProjectsError(null);

      try {
        const response = await fetch("/api/admin/projects", {
          signal: controller.signal,
          credentials: "same-origin",
          cache: "no-store",
        });

        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }

        const data: ProjectsResponse = await response.json();

        if (!Array.isArray(data.projects)) {
          throw new Error("Ongeldig antwoord van de API");
        }

        if (!controller.signal.aborted) {
          setProjects(data.projects);
        }
      } catch (error) {
        if (!controller.signal.aborted) {
          setProjectsError(
            error instanceof Error
              ? `Projecten ophalen mislukt: ${error.message}`
              : "Projecten ophalen mislukt",
          );
        }
      } finally {
        if (!controller.signal.aborted) {
          setProjectsLoading(false);
        }
      }
    }

    void loadProjects();

    return () => controller.abort();
  }, [refreshKey]);

  function updateField<K extends keyof ProjectForm>(
    field: K,
    value: ProjectForm[K],
  ) {
    setForm((current) => ({
      ...current,
      [field]: value,
    }));
  }

  function startEditing(project: Project) {
    setEditingId(project.id);

    setForm({
      title: project.title,
      description: project.description ?? "",
      client: project.client ?? "",
      year: project.year
        ? String(project.year)
        : String(new Date().getFullYear()),
      status: project.status,
    });

    setSaveMessage("");
    setSaveError("");

    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  function cancelEditing() {
    setEditingId(null);
    setForm(createEmptyForm());
    setSaveMessage("");
    setSaveError("");
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (isSaving) return;

    setIsSaving(true);
    setSaveMessage("");
    setSaveError("");

    const isEditing = editingId !== null;

    const endpoint = isEditing
      ? `/api/admin/projects/${editingId}`
      : "/api/admin/projects";

    try {
      const response = await fetch(endpoint, {
        method: isEditing ? "PUT" : "POST",
        headers: {
          "Content-Type": "application/json",
        },
        credentials: "same-origin",
        body: JSON.stringify(form),
      });

      const data: SaveResponse = await response.json();

      if (!response.ok) {
        throw new Error(
          data.error ?? `Opslaan mislukt (HTTP ${response.status})`,
        );
      }

      setSaveMessage(
        data.message ??
          (isEditing ? "Project bijgewerkt." : "Project opgeslagen."),
      );

      setEditingId(null);
      setForm(createEmptyForm());

      // Vernieuw de lijst met concepten en gepubliceerde projecten.
      setRefreshKey((current) => current + 1);
    } catch (error) {
      setSaveError(
        error instanceof Error
          ? error.message
          : "Project kon niet worden opgeslagen.",
      );
    } finally {
      setIsSaving(false);
    }
  }

  return (
    <section className="admin-page">
      <div className="admin-container">
        <div className="admin-heading">
          <div>
            <p className="hero-label">RGB VISUALS CMS</p>
            <h1>Projecten</h1>
            <p>Maak projecten aan en bewerk bestaande projecten.</p>
          </div>
        </div>

        <div className="admin-notice">
          Conceptprojecten zijn alleen zichtbaar in het CMS. Gepubliceerde
          projecten verschijnen ook via de openbare projecten-API.
        </div>

        <div className="admin-layout">
          <div className="admin-panel">
            <h2>
              {editingId !== null
                ? `Project #${editingId} bewerken`
                : "Nieuw project"}
            </h2>

            <form onSubmit={handleSubmit} className="admin-form">
              <label htmlFor="project-title">Projectnaam</label>
              <input
                id="project-title"
                type="text"
                maxLength={150}
                value={form.title}
                onChange={(event) => updateField("title", event.target.value)}
                placeholder="Bijvoorbeeld: Tuinman Piet"
                required
              />

              <label htmlFor="project-description">Korte beschrijving</label>
              <textarea
                id="project-description"
                maxLength={10000}
                value={form.description}
                onChange={(event) =>
                  updateField("description", event.target.value)
                }
                placeholder="Waar gaat het project over?"
                rows={4}
                required
              />

              <label htmlFor="project-client">Opdrachtgever</label>
              <input
                id="project-client"
                type="text"
                maxLength={150}
                value={form.client}
                onChange={(event) => updateField("client", event.target.value)}
                placeholder="Naam van de opdrachtgever"
                required
              />

              <label htmlFor="project-year">Jaar</label>
              <input
                id="project-year"
                type="number"
                min="2000"
                max="2100"
                value={form.year}
                onChange={(event) => updateField("year", event.target.value)}
                required
              />

              <label htmlFor="project-status">Status</label>
              <select
                id="project-status"
                value={form.status}
                onChange={(event) =>
                  updateField(
                    "status",
                    event.target.value as ProjectForm["status"],
                  )
                }
              >
                <option value="draft">Concept</option>
                <option value="published">Gepubliceerd</option>
              </select>

              {saveError && <p role="alert">{saveError}</p>}
              {saveMessage && <p role="status">{saveMessage}</p>}

              <button
                type="submit"
                className="admin-submit"
                disabled={isSaving}
              >
                {isSaving
                  ? "Bezig met opslaan..."
                  : editingId !== null
                    ? "Wijzigingen opslaan"
                    : "Project opslaan"}
              </button>

              {editingId !== null && (
                <button
                  type="button"
                  onClick={cancelEditing}
                  disabled={isSaving}
                >
                  Bewerken annuleren
                </button>
              )}
            </form>
          </div>

          <div className="admin-panel">
            <h2>Voorbeeld</h2>

            <div className="admin-preview">
              <span className="admin-status">
                {form.status === "draft" ? "Concept" : "Gepubliceerd"}
              </span>

              <h3>{form.title || "Projectnaam"}</h3>

              <p>{form.description || "Projectbeschrijving"}</p>

              <div className="admin-preview-meta">
                <span>Opdrachtgever: {form.client || "—"}</span>
                <span>Jaar: {form.year || "—"}</span>
              </div>
            </div>
          </div>
        </div>

        <div className="admin-panel">
          <h2>Alle projecten</h2>

          {projectsLoading ? (
            <p role="status">Projecten laden…</p>
          ) : projectsError ? (
            <p role="alert">{projectsError}</p>
          ) : projects.length === 0 ? (
            <p className="admin-empty">
              Er staan nog geen projecten in de database.
            </p>
          ) : (
            <div className="cms-module-grid">
              {projects.map((project) => (
                <div className="cms-module-card" key={project.id}>
                  <span className="admin-status">
                    {project.status === "draft" ? "Concept" : "Gepubliceerd"}
                  </span>

                  <h3>{project.title}</h3>

                  <p>
                    {project.summary ??
                      project.description ??
                      "Geen beschrijving beschikbaar."}
                  </p>

                  <small>
                    {project.client ?? "Geen opdrachtgever"} ·{" "}
                    {project.year ?? "Geen jaar"}
                  </small>

                  <p>
                    <small>Slug: {project.slug}</small>
                  </p>

                  <button
                    type="button"
                    onClick={() => startEditing(project)}
                    disabled={isSaving}
                  >
                    Bewerken
                  </button>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </section>
  );
}
