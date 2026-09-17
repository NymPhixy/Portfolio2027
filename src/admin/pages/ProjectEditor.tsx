import { useEffect, useState } from "react";
import type { FormEvent } from "react";
import CoverUploader from "../components/CoverUploader";
// import GalleryUploader from "../components/GalleryUploader";
// import ProjectDocumentsEditor from "../components/ProjectDocumentsEditor";
// import ProjectLinksEditor from "../components/ProjectLinksEditor";

type ProjectForm = {
  title: string;
  description: string;
  client: string;
  year: string;
  status: "draft" | "published";
  role: string;
  technologies: string;
  challenge: string;
  solution: string;
  result: string;
};

type Project = {
  id: number;
  title: string;
  slug: string;
  summary: string | null;
  description: string | null;
  client: string | null;
  year: number | null;
  status: "draft" | "published";
  role: string | null;
  technologies: string | null;
  challenge: string | null;
  solution: string | null;
  result: string | null;
  cover_image: string | null;
};

type ProjectsResponse = {
  projects: Project[];
};

type SaveResponse = {
  message?: string;
  error?: string;
  project?: {
    id?: number;
  };
};

function createEmptyForm(): ProjectForm {
  return {
    title: "",
    description: "",
    client: "",
    year: String(new Date().getFullYear()),
    status: "draft",
    role: "",
    technologies: "",
    challenge: "",
    solution: "",
    result: "",
  };
}

function technologiesToText(value: string | null): string {
  if (!value) return "";

  try {
    const parsed: unknown = JSON.parse(value);

    if (
      Array.isArray(parsed) &&
      parsed.every((item) => typeof item === "string")
    ) {
      return parsed.join(", ");
    }
  } catch {
    // Ongeldige JSON wordt niet als invoer overgenomen.
  }

  return "";
}

export default function ProjectEditor() {
  const [form, setForm] = useState<ProjectForm>(createEmptyForm);
  const [editingId, setEditingId] = useState<number | null>(null);
  const isEditing = editingId !== null;

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
      role: project.role ?? "",
      technologies: technologiesToText(project.technologies),
      challenge: project.challenge ?? "",
      solution: project.solution ?? "",
      result: project.result ?? "",
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

    setSaveMessage("");
    setSaveError("");

    const technologies = form.technologies
      .split(",")
      .map((technology) => technology.trim())
      .filter((technology) => technology !== "");

    if (
      technologies.length > 20 ||
      technologies.some((technology) => technology.length > 60)
    ) {
      setSaveError("Gebruik maximaal 20 technologieën van maximaal 60 tekens.");
      return;
    }

    setIsSaving(true);

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
        body: JSON.stringify({
          ...form,
          technologies,
        }),
      });

      const data: SaveResponse = await response.json();

      if (!response.ok) {
        throw new Error(
          data.error ?? `Opslaan mislukt (HTTP ${response.status})`,
        );
      }

      const savedProjectId = data.project?.id;

      if (
        typeof savedProjectId !== "number" ||
        !Number.isInteger(savedProjectId) ||
        savedProjectId <= 0
      ) {
        throw new Error("De API gaf geen geldig project-ID terug.");
      }

      setSaveMessage(
        data.message ??
          (isEditing ? "Project bijgewerkt." : "Project opgeslagen."),
      );

      setEditingId(savedProjectId);
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
            <p>Maak projecten aan en beheer je casestudy's.</p>
          </div>
        </div>

        <div className="admin-layout">
          <div className="admin-panel">
            <h2>
              {isEditing ? `Project #${editingId} bewerken` : "Nieuw project"}
            </h2>

            <form onSubmit={handleSubmit} className="admin-form">
              <label htmlFor="project-title">Projectnaam</label>
              <input
                id="project-title"
                type="text"
                maxLength={150}
                value={form.title}
                onChange={(event) => updateField("title", event.target.value)}
                required
              />

              <label htmlFor="project-description">Korte beschrijving</label>
              <textarea
                id="project-description"
                maxLength={10000}
                rows={4}
                value={form.description}
                onChange={(event) =>
                  updateField("description", event.target.value)
                }
                required
              />

              <label htmlFor="project-client">Opdrachtgever</label>
              <input
                id="project-client"
                type="text"
                maxLength={150}
                value={form.client}
                onChange={(event) => updateField("client", event.target.value)}
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

              <h2 style={{ marginTop: "32px" }}>Casestudy</h2>

              <label htmlFor="project-role">Mijn rol</label>
              <input
                id="project-role"
                type="text"
                maxLength={150}
                placeholder="Bijvoorbeeld: UX/UI Designer"
                value={form.role}
                onChange={(event) => updateField("role", event.target.value)}
              />

              <label htmlFor="project-technologies">Technologieën</label>
              <input
                id="project-technologies"
                type="text"
                placeholder="React, TypeScript, PHP, MySQL"
                value={form.technologies}
                onChange={(event) =>
                  updateField("technologies", event.target.value)
                }
              />
              <small>Scheid technologieën met een komma.</small>

              <label htmlFor="project-challenge">De uitdaging</label>
              <textarea
                id="project-challenge"
                rows={5}
                maxLength={10000}
                value={form.challenge}
                onChange={(event) =>
                  updateField("challenge", event.target.value)
                }
                placeholder="Welk probleem wilde je oplossen?"
              />

              <label htmlFor="project-solution">De oplossing</label>
              <textarea
                id="project-solution"
                rows={5}
                maxLength={10000}
                value={form.solution}
                onChange={(event) =>
                  updateField("solution", event.target.value)
                }
                placeholder="Hoe heb je het aangepakt?"
              />

              <label htmlFor="project-result">Het resultaat</label>
              <textarea
                id="project-result"
                rows={5}
                maxLength={10000}
                value={form.result}
                onChange={(event) => updateField("result", event.target.value)}
                placeholder="Wat heeft het project opgeleverd?"
              />

              {saveError && <p role="alert">{saveError}</p>}
              {saveMessage && <p role="status">{saveMessage}</p>}

              <button
                type="submit"
                className="admin-submit"
                disabled={isSaving}
              >
                {isSaving
                  ? "Bezig met opslaan..."
                  : isEditing
                    ? "Wijzigingen opslaan"
                    : "Project opslaan"}
              </button>

              {isEditing && (
                <button
                  type="button"
                  onClick={cancelEditing}
                  disabled={isSaving}
                >
                  Bewerken annuleren
                </button>
              )}
            </form>

            {editingId !== null && (
              <>
                <CoverUploader
                  key={editingId}
                  projectId={editingId}
                  isPublished={
                    projects.find((project) => project.id === editingId)
                      ?.status === "published"
                  }
                  coverImage={
                    projects.find((project) => project.id === editingId)
                      ?.cover_image ?? null
                  }
                  onUploaded={() => setRefreshKey((current) => current + 1)}
                />
                {/* <GalleryUploader
                  projectId={editingId}
                  onChanged={() => setRefreshKey((current) => current + 1)}
                /> */}
                {/* <ProjectLinksEditor
                  key={`links-${editingId}`}
                  projectId={editingId}
                  onChanged={() => setRefreshKey((current) => current + 1)}
                /> */}
                {/* <ProjectDocumentsEditor
                  key={`documents-${editingId}`}
                  projectId={editingId}
                  onChanged={() => setRefreshKey((current) => current + 1)}
                />
                {/* <ProjectDocumentsEditor
                  key={`documents-${editingId}`}
                  projectId={editingId}
                  onChanged={() => setRefreshKey((current) => current + 1)}
                /> */}
              </>
            )}
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

                {form.role && <span>Mijn rol: {form.role}</span>}

                {form.technologies && (
                  <span>Technologieën: {form.technologies}</span>
                )}
              </div>

              {form.challenge && (
                <>
                  <h3>De uitdaging</h3>
                  <p>{form.challenge}</p>
                </>
              )}

              {form.solution && (
                <>
                  <h3>De oplossing</h3>
                  <p>{form.solution}</p>
                </>
              )}

              {form.result && (
                <>
                  <h3>Het resultaat</h3>
                  <p>{form.result}</p>
                </>
              )}
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
                    {project.client ?? "Geen opdrachtgever"}
                    {" · "}
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
