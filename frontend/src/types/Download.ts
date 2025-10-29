export interface Download {
  id: string;
  filename: string;
  url: string;
  status: 'pending' | 'downloading' | 'paused' | 'completed' | 'failed' | 'cancelled';
  progress?: number; // 0-100
  file_size: number | null; // in bytes
  downloaded_size: number; // in bytes
  file_path: string | null;
  created_at: string;
  updated_at?: string;
  error_message?: string;
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
  file_size: number;
  downloaded_size: number;
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
  field: 'filename' | 'url' | 'status' | 'progress' | 'file_size' | 'downloaded_size' | 'created_at' | 'updated_at';
  order: 'asc' | 'desc';
}
