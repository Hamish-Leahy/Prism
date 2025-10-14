export interface Download {
  id: string;
  filename: string;
  url: string;
  status: 'pending' | 'downloading' | 'paused' | 'completed' | 'failed' | 'cancelled';
  progress: number; // 0-100
  totalSize: number | null; // in bytes
  downloadedSize: number; // in bytes
  filePath: string | null;
  createdAt: string;
  updatedAt: string;
  error?: string;
  speed?: number; // bytes per second
  eta?: number; // estimated time remaining in seconds
}

export interface DownloadStats {
  total: number;
  downloading: number;
  completed: number;
  paused: number;
  failed: number;
  cancelled: number;
  totalSize: number;
  downloadedSize: number;
}

export interface DownloadFilters {
  status?: Download['status'];
  dateRange?: {
    start: string;
    end: string;
  };
  filename?: string;
  url?: string;
}

export interface DownloadSort {
  field: 'filename' | 'url' | 'status' | 'progress' | 'totalSize' | 'downloadedSize' | 'createdAt' | 'updatedAt';
  order: 'asc' | 'desc';
}
