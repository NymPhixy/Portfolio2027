import { useEffect, useState } from "react";

import Navbar from "./components/Navbar";
import Hero from "./sections/Hero";
import Projects from "./sections/Projects";
import ProjectDetail from "./sections/ProjectDetail";
import AdminDashboard from "./admin/AdminDashboard";

import { projects as localProjects } from "./data/projects";
import type { Project } from "./types/project";
import heroImg from "./assets/hero.png";

import "./App.css";

type ApiProject = {
  id: number;
  title: string;
  slug: string;
  summary: string | null;
  description: string | null;
  client: string | null;
  year: number | null;
  cover_image: string | null;

  role: string | null;
  technologies: string | null;
  challenge: string | null;
  solution: string | null;
  result: string | null;
};

type ProjectsApiResponse = {
  projects: ApiProject[];
};

/**
 * Technologieën staan in MySQL als JSON-tekst.
 * Bijvoorbeeld: '["React","TypeScript","PHP"]'
 */
function parseTechnologies(value: string | null): string[] {
  if (!value) {
    return [];
  }

  try {
    const parsed: unknown = JSON.parse(value);

    if (
      Array.isArray(parsed) &&
      parsed.every((item) => typeof item === "string")
    ) {
      return parsed;
    }
  } catch {
    console.warn("Een project bevat ongeldige technologiegegevens.");
  }

  return [];
}

/**
 * Zet een project uit MySQL om naar het Project-type
 * dat ProjectCard en ProjectDetail gebruiken.
 */
function convertApiProject(project: ApiProject): Project {
  const hasImage = Boolean(project.cover_image);

  return {
    // Voorkomt botsingen met ID's uit src/data/projects.ts.
    id: `cms-${project.id}`,

    title: project.title,

    description:
      project.summary || project.description || "Beschrijving volgt.",

    year: project.year,

    image: hasImage ? `/api/projects/${project.id}/cover` : heroImg,
    imageIsPlaceholder: !hasImage,

    technologies: parseTechnologies(project.technologies),

    status: "published",

    client: project.client ?? "",
    role: project.role ?? "",

    challenge: project.challenge ?? "",
    solution: project.solution ?? "",
    result: project.result ?? "",

    // Afbeeldingengalerij koppelen we in een volgende stap.
    gallery: [],
  };
}

function App() {
  const params = new URLSearchParams(window.location.search);

  const projectId = params.get("project");
  const adminPreview = params.get("admin") === "preview";

  const [allProjects, setAllProjects] = useState<Project[]>(localProjects);
  const [projectsLoading, setProjectsLoading] = useState(true);
  const [projectsError, setProjectsError] = useState(false);

  useEffect(() => {
    const controller = new AbortController();

    async function loadProjects() {
      try {
        const response = await fetch("/api/projects", {
          signal: controller.signal,
          credentials: "same-origin",
          cache: "no-store",
        });

        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }

        const data: ProjectsApiResponse = await response.json();

        if (!Array.isArray(data.projects)) {
          throw new Error("Ongeldig API-antwoord");
        }

        const cmsProjects = data.projects.map(convertApiProject);

        if (!controller.signal.aborted) {
          setAllProjects([...localProjects, ...cmsProjects]);
          setProjectsError(false);
        }
      } catch (error) {
        if (!controller.signal.aborted) {
          console.error("CMS-projecten ophalen mislukt:", error);
          setProjectsError(true);
        }
      } finally {
        if (!controller.signal.aborted) {
          setProjectsLoading(false);
        }
      }
    }

    void loadProjects();

    return () => controller.abort();
  }, []);

  // Het CMS is voorlopig alleen bereikbaar in de ontwikkelomgeving.
  if (adminPreview && import.meta.env.DEV) {
    return (
      <main>
        <AdminDashboard />
      </main>
    );
  }

  const selectedProject = allProjects.find(
    (project) =>
      project.status === "published" && String(project.id) === projectId,
  );

  // Projectdetailpagina
  if (projectId !== null) {
    return (
      <>
        <Navbar />

        <main>
          {selectedProject ? (
            <ProjectDetail project={selectedProject} />
          ) : projectsLoading ? (
            <section className="project-not-found">
              <h1>Project laden...</h1>
            </section>
          ) : (
            <section className="project-not-found">
              <h1>
                {projectsError
                  ? "Project tijdelijk niet beschikbaar"
                  : "Project niet gevonden"}
              </h1>

              <a href="/">Terug naar portfolio</a>
            </section>
          )}
        </main>
      </>
    );
  }

  // Homepage
  return (
    <>
      <Navbar />

      <main>
        <Hero />

        <Projects projects={allProjects} />
      </main>
    </>
  );
}

export default App;
