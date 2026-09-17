export type ProjectGalleryImage = {
  url: string;
  altText?: string | null;
};

export type ProjectGalleryItem = string | ProjectGalleryImage;

export type ProjectLinkType = "document" | "website" | "prototype" | "other";

export type ProjectLinkItem = {
  title: string;
  url: string;
  type: ProjectLinkType;
};

export type ProjectDocumentItem = {
  id: number;
  title: string;
  originalName: string;
  downloadUrl: string;
};

export type Project = {
  id: number | string;
  title: string;
  description: string;
  year: number | null;
  image: string;
  imageIsPlaceholder?: boolean;
  technologies: string[];
  status: "draft" | "published";

  // Inhoud voor de casestudy
  client: string;
  role: string;
  challenge: string;
  solution: string;
  result: string;
  gallery: ProjectGalleryItem[];
  links: ProjectLinkItem[];
  documents: ProjectDocumentItem[];
  cmsProjectId?: number;
};
