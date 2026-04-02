/**
 * Models and types for file-upload component.
 */

/** Represents a file being uploaded */
export interface AfUploadFile {
  /** Original File object */
  file: File;
  /** Display name */
  name: string;
  /** File size in bytes */
  size: number;
  /** Upload progress 0-100 */
  progress: number;
  /** Upload state */
  status: 'pending' | 'uploading' | 'done' | 'cancelled' | 'error';
  /** Internal id */
  id: string;
}
