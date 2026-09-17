import { useEffect, useState } from "react";
import type {
  Project,
  ProjectDocumentItem,
  ProjectGalleryItem,
  ProjectLinkItem,
  ProjectLinkType,
} from "../types/project";

type ProjectDetailProps = {
  project: Project;
};

type PublicGalleryResponse = {
  images?: Array<{
    image_url: string;
    alt_text: string | null;
  }>;
  error?: string;
};

type PublicLinksResponse = {
  links?: ProjectLinkItem[];
  error?: string;
};

type PublicDocumentsResponse = {
  documents?: Array<{
    id: number;
    title: string;
    original_name: string;
    download_url: string;
  }>;
  error?: string;
};

const linkTypeLabels: Record<ProjectLinkType, string> = {
  document: "Document",
  website: "Website",
  prototype: "Prototype",
  other: "Overig",
};

function galleryImageDetails(image: ProjectGalleryItem): {
  url: string;
  altText: string | null;
} {
  if (typeof image === "string") {
    return {
      url: image,
      altText: null,
    };
  }

  return {
    url: image.url,
    altText: image.altText ?? null,
  };
}

export default function ProjectDetail({ project }: ProjectDetailProps) {
  const [gallery, setGallery] = useState<ProjectGalleryItem[]>(project.gallery);
  const [galleryLoading, setGalleryLoading] = useState(
    project.cmsProjectId !== undefined,
  );
  const [galleryError, setGalleryError] = useState("");
  const [links, setLinks] = useState<ProjectLinkItem[]>(project.links);
  const [linksLoading, setLinksLoading] = useState(
    project.cmsProjectId !== undefined,
  );
  const [linksError, setLinksError] = useState("");
  const [documents, setDocuments] = useState<ProjectDocumentItem[]>(
    project.documents,
  );
  const [documentsLoading, setDocumentsLoading] = useState(
    project.cmsProjectId !== undefined,
  );
  const [documentsError, setDocumentsError] = useState("");

  useEffect(() => {
    const controller = new AbortController();

    if (project.cmsProjectId === undefined) {
      return () => controller.abort();
    }

    async function loadGallery() {
      try {
        const response = await fetch(
          `/api/projects/${project.cmsProjectId}/images`,
          {
            signal: controller.signal,
            credentials: "same-origin",
            cache: "no-store",
          },
        );
        const data: PublicGalleryResponse = await response.json();

        if (!response.ok || !Array.isArray(data.images)) {
          throw new Error(
            data.error ?? "Projectbeelden konden niet worden geladen.",
          );
        }

        if (!controller.signal.aborted) {
          setGallery(
            data.images.map((image) => ({
              url: image.image_url,
              altText: image.alt_text,
            })),
          );
          setGalleryLoading(false);
        }
      } catch (error) {
        if (!controller.signal.aborted) {
          setGalleryError(
            error instanceof Error
              ? error.message
              : "Projectbeelden konden niet worden geladen.",
          );
          setGalleryLoading(false);
        }
      }
    }

    async function loadLinks() {
      try {
        const response = await fetch(
          `/api/projects/${project.cmsProjectId}/links`,
          {
            signal: controller.signal,
            credentials: "same-origin",
            cache: "no-store",
          },
        );
        const data: PublicLinksResponse = await response.json();

        if (!response.ok || !Array.isArray(data.links)) {
          throw new Error(
            data.error ?? "Projectlinks konden niet worden geladen.",
          );
        }

        if (!controller.signal.aborted) {
          setLinks(data.links);
          setLinksLoading(false);
        }
      } catch (error) {
        if (!controller.signal.aborted) {
          setLinksError(
            error instanceof Error
              ? error.message
              : "Projectlinks konden niet worden geladen.",
          );
          setLinksLoading(false);
        }
      }
    }

    async function loadDocuments() {
      try {
        const response = await fetch(
          `/api/projects/${project.cmsProjectId}/documents`,
          {
            signal: controller.signal,
            credentials: "same-origin",
            cache: "no-store",
          },
        );
        const data: PublicDocumentsResponse = await response.json();

        if (!response.ok || !Array.isArray(data.documents)) {
          throw new Error(
            data.error ?? "PDF-documenten konden niet worden geladen.",
          );
        }

        if (!controller.signal.aborted) {
          setDocuments(
            data.documents.map((document) => ({
              id: document.id,
              title: document.title,
              originalName: document.original_name,
              downloadUrl: document.download_url,
            })),
          );
          setDocumentsLoading(false);
        }
      } catch (error) {
        if (!controller.signal.aborted) {
          setDocumentsError(
            error instanceof Error
              ? error.message
              : "PDF-documenten konden niet worden geladen.",
          );
          setDocumentsLoading(false);
        }
      }
    }

    void loadGallery();
    void loadLinks();
    void loadDocuments();

    return () => controller.abort();
  }, [project.cmsProjectId]);

  const hasMeta =
    Boolean(project.client) ||
    Boolean(project.role) ||
    project.technologies.length > 0;

  const hasStory =
    Boolean(project.challenge) ||
    Boolean(project.solution) ||
    Boolean(project.result);

  const hasVisibleLinks = !linksLoading && !linksError && links.length > 0;
  const hasVisibleDocuments =
    !documentsLoading && !documentsError && documents.length > 0;

  return (
    <section className="project-detail">
      <div className="project-detail-container">
        <a href="/" className="project-back">
          ← Terug naar portfolio
        </a>

        <p className="hero-label">
          CASESTUDY
          {project.year !== null ? ` · ${project.year}` : ""}
        </p>

        <h1>{project.title}</h1>

        <p className="project-detail-intro">{project.description}</p>

        <img
          className="project-detail-cover"
          src={project.image}
          alt={
            project.imageIsPlaceholder
              ? "RGB Visuals standaardafbeelding; projectfoto volgt"
              : `Projectafbeelding van ${project.title}`
          }
        />

        {hasMeta && (
          <div className="project-detail-meta">
            {project.client && (
              <div>
                <h2>Opdrachtgever</h2>
                <p>{project.client}</p>
              </div>
            )}

            {project.role && (
              <div>
                <h2>Mijn rol</h2>
                <p>{project.role}</p>
              </div>
            )}

            {project.technologies.length > 0 && (
              <div>
                <h2>Technologieën</h2>
                <p>{project.technologies.join(", ")}</p>
              </div>
            )}
          </div>
        )}

        {hasStory ? (
          <div className="project-detail-story">
            {project.challenge && (
              <>
                <h2>De uitdaging</h2>
                <p>{project.challenge}</p>
              </>
            )}

            {project.solution && (
              <>
                <h2>De oplossing</h2>
                <p>{project.solution}</p>
              </>
            )}

            {project.result && (
              <>
                <h2>Het resultaat</h2>
                <p>{project.result}</p>
              </>
            )}
          </div>
        ) : (
          <p className="project-detail-intro">
            Een uitgebreide casestudy volgt.
          </p>
        )}

        {galleryLoading && (
          <p className="project-detail-intro" role="status">
            Projectbeelden laden...
          </p>
        )}

        {galleryError && (
          <p className="project-detail-intro" role="alert">
            {galleryError}
          </p>
        )}

        {!galleryLoading && !galleryError && gallery.length > 0 && (
          <div className="project-detail-gallery">
            <h2>Projectbeelden</h2>

            <div className="project-gallery-grid">
              {gallery.map((image, index) => {
                const imageDetails = galleryImageDetails(image);

                return (
                  <img
                    key={`${imageDetails.url}-${index}`}
                    src={imageDetails.url}
                    alt={
                      imageDetails.altText ||
                      `${project.title}, afbeelding ${index + 1}`
                    }
                    loading="lazy"
                  />
                );
              })}
            </div>
          </div>
        )}

        {linksLoading && (
          <p className="project-detail-intro" role="status">
            Projectlinks laden...
          </p>
        )}

        {linksError && (
          <p className="project-detail-intro" role="alert">
            {linksError}
          </p>
        )}

        {documentsLoading && (
          <p className="project-detail-intro" role="status">
            PDF-documenten laden...
          </p>
        )}

        {documentsError && (
          <p className="project-detail-intro" role="alert">
            {documentsError}
          </p>
        )}

        {(hasVisibleLinks || hasVisibleDocuments) && (
          <div className="project-detail-links">
            <h2>Bekijk het project</h2>

            <div className="project-links-list">
              {hasVisibleLinks &&
                links.map((link) => (
                  <a
                    className="project-link-item"
                    href={link.url}
                    key={`${link.url}-${link.title}`}
                    target="_blank"
                    rel="noopener noreferrer"
                  >
                    <span className="project-link-item-content">
                      <strong>{link.title}</strong>
                      <small>{linkTypeLabels[link.type]} · Externe link</small>
                    </span>
                    <span
                      className="project-link-item-external"
                      aria-hidden="true"
                    >
                      Extern openen ↗
                    </span>
                  </a>
                ))}

              {hasVisibleDocuments &&
                documents.map((document) => (
                  <a
                    className="project-link-item project-document-item"
                    href={document.downloadUrl}
                    key={`document-${document.id}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    download
                  >
                    <span className="project-link-item-content">
                      <strong>{document.title}</strong>
                      <small>PDF · Opgeslagen op RGB Visuals</small>
                    </span>
                    <span className="project-link-item-external">
                      PDF downloaden ↓
                    </span>
                  </a>
                ))}
            </div>
          </div>
        )}
      </div>
    </section>
  );
}
