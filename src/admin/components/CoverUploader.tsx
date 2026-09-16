import { useRef, useState } from "react";
import type { ChangeEvent } from "react";

type CoverUploaderProps = {
  projectId: number;
  isPublished: boolean;
  coverImage: string | null;
  onUploaded: () => void;
};

type UploadResponse = {
  message?: string;
  error?: string;
};

const MAX_FILE_SIZE = 2 * 1024 * 1024;

const ALLOWED_FILE_TYPES = ["image/jpeg", "image/png", "image/webp"];

export default function CoverUploader({
  projectId,
  isPublished,
  coverImage,
  onUploaded,
}: CoverUploaderProps) {
  const inputRef = useRef<HTMLInputElement>(null);

  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [isUploading, setIsUploading] = useState(false);
  const [error, setError] = useState("");
  const [message, setMessage] = useState("");
  const [uploaded, setUploaded] = useState(false);
  const [imageVersion, setImageVersion] = useState(0);

  const hasCover = Boolean(coverImage) || uploaded;
  const fileInputId = `cover-file-${projectId}`;

  function handleFileChange(event: ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0] ?? null;

    setError("");
    setMessage("");

    if (!file) {
      setSelectedFile(null);
      return;
    }

    if (file.size > MAX_FILE_SIZE) {
      setSelectedFile(null);
      setError("De afbeelding mag maximaal 2 MB zijn.");
      event.target.value = "";
      return;
    }

    if (!ALLOWED_FILE_TYPES.includes(file.type)) {
      setSelectedFile(null);
      setError("Kies een JPG-, PNG- of WebP-afbeelding.");
      event.target.value = "";
      return;
    }

    setSelectedFile(file);
  }

  async function handleUpload() {
    if (!selectedFile || isUploading) {
      return;
    }

    setIsUploading(true);
    setError("");
    setMessage("");

    const formData = new FormData();
    formData.append("cover", selectedFile);

    try {
      const response = await fetch(`/api/admin/projects/${projectId}/cover`, {
        method: "POST",
        headers: {
          "X-RGB-Upload": "1",
        },
        credentials: "same-origin",
        body: formData,
      });

      const data: UploadResponse = await response.json();

      if (!response.ok) {
        throw new Error(
          data.error ?? `Upload mislukt (HTTP ${response.status})`,
        );
      }

      setMessage(data.message ?? "Omslagafbeelding opgeslagen.");
      setUploaded(true);
      setImageVersion(Date.now());
      setSelectedFile(null);

      if (inputRef.current) {
        inputRef.current.value = "";
      }

      onUploaded();
    } catch (uploadError) {
      setError(
        uploadError instanceof Error
          ? uploadError.message
          : "Afbeelding uploaden is mislukt.",
      );
    } finally {
      setIsUploading(false);
    }
  }

  return (
    <div className="admin-cover-uploader">
      <h2>Omslagafbeelding</h2>

      <p>Upload een JPG-, PNG- of WebP-afbeelding van maximaal 2 MB.</p>

      <div className="admin-cover-file-picker">
        <input
          ref={inputRef}
          id={fileInputId}
          className="admin-cover-file-input"
          type="file"
          accept="image/jpeg,image/png,image/webp"
          onChange={handleFileChange}
          disabled={isUploading}
          aria-describedby={`${fileInputId}-description`}
        />

        <label
          htmlFor={fileInputId}
          className={`admin-cover-file-button${
            isUploading ? " is-disabled" : ""
          }`}
        >
          Bestand kiezen
        </label>

        <span
          id={`${fileInputId}-description`}
          className="admin-cover-file-name"
        >
          {selectedFile ? selectedFile.name : "Geen bestand geselecteerd"}
        </span>
      </div>

      {error && <p role="alert">{error}</p>}
      {message && <p role="status">{message}</p>}

      <button
        type="button"
        className="admin-submit admin-cover-upload-button"
        onClick={() => void handleUpload()}
        disabled={!selectedFile || isUploading}
      >
        {isUploading ? "Afbeelding uploaden..." : "Afbeelding uploaden"}
      </button>

      {hasCover && isPublished && (
        <div className="admin-cover-preview">
          <h3>Huidige omslagafbeelding</h3>

          <img
            src={`/api/projects/${projectId}/cover?v=${imageVersion}`}
            alt="Omslagafbeelding van het geselecteerde project"
          />
        </div>
      )}

      {hasCover && !isPublished && (
        <p>
          De afbeelding is opgeslagen. Publiceer het project om de
          omslagafbeelding op je website zichtbaar te maken.
        </p>
      )}
    </div>
  );
}
