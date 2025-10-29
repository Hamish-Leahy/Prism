import React, { useState } from 'react';
import { useDownloads } from '../hooks/useDownloads';

interface DownloadsPageProps {
  onClose: () => void;
}

export const DownloadsPage: React.FC<DownloadsPageProps> = ({ onClose }) => {
  const { 
    downloads, 
    pauseDownload, 
    resumeDownload, 
    cancelDownload, 
    deleteDownload, 
    clearCompletedDownloads,
    getProgress,
    formatFileSize
  } = useDownloads();
  const [filter, setFilter] = useState<'all' | 'downloading' | 'completed' | 'paused' | 'failed'>('all');
  const [sortBy, setSortBy] = useState<'date' | 'name' | 'size' | 'status'>('date');
  const [sortOrder, setSortOrder] = useState<'asc' | 'desc'>('desc');

  const filteredDownloads = downloads.filter(download => {
    if (filter === 'all') return true;
    return download.status === filter;
  });

  const sortedDownloads = [...filteredDownloads].sort((a, b) => {
    let comparison = 0;
    
    switch (sortBy) {
      case 'date':
        comparison = new Date(a.created_at).getTime() - new Date(b.created_at).getTime();
        break;
      case 'name':
        comparison = a.filename.localeCompare(b.filename);
        break;
      case 'size':
        comparison = (a.file_size || 0) - (b.file_size || 0);
        break;
      case 'status':
        comparison = a.status.localeCompare(b.status);
        break;
    }
    
    return sortOrder === 'asc' ? comparison : -comparison;
  });

  const formatDate = (dateString: string): string => {
    const date = new Date(dateString);
    const now = new Date();
    const diffInHours = (now.getTime() - date.getTime()) / (1000 * 60 * 60);
    
    if (diffInHours < 1) {
      return 'Just now';
    } else if (diffInHours < 24) {
      return `${Math.floor(diffInHours)} hours ago`;
    } else if (diffInHours < 48) {
      return 'Yesterday';
    } else {
      return date.toLocaleDateString();
    }
  };

  const getStatusColor = (status: string): string => {
    switch (status) {
      case 'downloading':
        return 'text-blue-600 bg-blue-100';
      case 'completed':
        return 'text-green-600 bg-green-100';
      case 'paused':
        return 'text-yellow-600 bg-yellow-100';
      case 'failed':
        return 'text-red-600 bg-red-100';
      case 'cancelled':
        return 'text-gray-600 bg-gray-100';
      default:
        return 'text-gray-600 bg-gray-100';
    }
  };

  const handleDownloadAction = (downloadId: string, action: 'pause' | 'resume' | 'cancel' | 'delete') => {
    switch (action) {
      case 'pause':
        pauseDownload(downloadId);
        break;
      case 'resume':
        resumeDownload(downloadId);
        break;
      case 'cancel':
        cancelDownload(downloadId);
        break;
      case 'delete':
        deleteDownload(downloadId);
        break;
    }
  };

  const handleClearCompleted = () => {
    clearCompletedDownloads();
  };

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div className="bg-white rounded-lg max-w-6xl w-full mx-4 max-h-[80vh] flex flex-col">
        {/* Header */}
        <div className="flex justify-between items-center p-6 border-b border-gray-200">
          <h2 className="text-2xl font-bold">Downloads</h2>
          <button
            onClick={onClose}
            className="text-gray-500 hover:text-gray-700 transition-colors"
          >
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        {/* Controls */}
        <div className="p-6 border-b border-gray-200">
          <div className="flex flex-wrap items-center gap-4">
            {/* Filter */}
            <div className="flex items-center space-x-2">
              <label className="text-sm font-medium text-gray-700">Filter:</label>
              <select
                value={filter}
                onChange={(e) => setFilter(e.target.value as any)}
                className="px-3 py-1 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="all">All</option>
                <option value="downloading">Downloading</option>
                <option value="completed">Completed</option>
                <option value="paused">Paused</option>
                <option value="failed">Failed</option>
              </select>
            </div>

            {/* Sort */}
            <div className="flex items-center space-x-2">
              <label className="text-sm font-medium text-gray-700">Sort by:</label>
              <select
                value={sortBy}
                onChange={(e) => setSortBy(e.target.value as any)}
                className="px-3 py-1 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="date">Date</option>
                <option value="name">Name</option>
                <option value="size">Size</option>
                <option value="status">Status</option>
              </select>
              <button
                onClick={() => setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc')}
                className="p-1 hover:bg-gray-100 rounded transition-colors"
                title={`Sort ${sortOrder === 'asc' ? 'descending' : 'ascending'}`}
              >
                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d={sortOrder === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7'} />
                </svg>
              </button>
            </div>

            {/* Actions */}
            <div className="flex items-center space-x-2 ml-auto">
              <button
                onClick={handleClearCompleted}
                className="px-3 py-1 text-sm bg-gray-500 text-white rounded hover:bg-gray-600 transition-colors"
              >
                Clear Completed
              </button>
            </div>
          </div>
        </div>

        {/* Downloads List */}
        <div className="flex-1 overflow-y-auto p-6">
          {sortedDownloads.length === 0 ? (
            <div className="text-center py-12">
              <svg className="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              <h3 className="text-lg font-medium text-gray-900 mb-2">No downloads</h3>
              <p className="text-gray-500">Your downloads will appear here</p>
            </div>
          ) : (
            <div className="space-y-3">
              {sortedDownloads.map((download) => (
                <div key={download.id} className="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                  <div className="flex items-start justify-between">
                    <div className="flex-1 min-w-0">
                      {/* File Info */}
                      <div className="flex items-center space-x-3 mb-2">
                        <div className="flex-shrink-0">
                          <svg className="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                          </svg>
                        </div>
                        <div className="flex-1 min-w-0">
                          <h4 className="text-sm font-medium text-gray-900 truncate">
                            {download.filename}
                          </h4>
                          <p className="text-sm text-gray-500 truncate">
                            {download.url}
                          </p>
                        </div>
                      </div>

                      {/* Progress Bar */}
                      {download.status === 'downloading' && (
                        <div className="mb-2">
                          <div className="flex justify-between text-xs text-gray-500 mb-1">
                            <span>{getProgress(download)}%</span>
                            <span>
                              {formatFileSize(download.downloaded_size)} / {download.file_size ? formatFileSize(download.file_size) : 'Unknown'}
                            </span>
                          </div>
                          <div className="w-full bg-gray-200 rounded-full h-2">
                            <div
                              className="bg-blue-600 h-2 rounded-full transition-all duration-300"
                              style={{ width: `${getProgress(download)}%` }}
                            />
                          </div>
                        </div>
                      )}

                      {/* Status and Date */}
                      <div className="flex items-center justify-between">
                        <div className="flex items-center space-x-2">
                          <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusColor(download.status)}`}>
                            {download.status.charAt(0).toUpperCase() + download.status.slice(1)}
                          </span>
                          <span className="text-xs text-gray-500">
                            {formatDate(download.created_at)}
                          </span>
                        </div>
                        
                        {/* File Size */}
                        {download.file_size && (
                          <span className="text-xs text-gray-500">
                            {formatFileSize(download.file_size)}
                          </span>
                        )}
                      </div>
                    </div>

                    {/* Actions */}
                    <div className="flex items-center space-x-2 ml-4">
                      {download.status === 'downloading' && (
                        <button
                          onClick={() => handleDownloadAction(download.id, 'pause')}
                          className="px-3 py-1 text-xs bg-yellow-500 text-white rounded hover:bg-yellow-600 transition-colors"
                        >
                          Pause
                        </button>
                      )}
                      {download.status === 'paused' && (
                        <button
                          onClick={() => handleDownloadAction(download.id, 'resume')}
                          className="px-3 py-1 text-xs bg-green-500 text-white rounded hover:bg-green-600 transition-colors"
                        >
                          Resume
                        </button>
                      )}
                      {(download.status === 'downloading' || download.status === 'paused') && (
                        <button
                          onClick={() => handleDownloadAction(download.id, 'cancel')}
                          className="px-3 py-1 text-xs bg-red-500 text-white rounded hover:bg-red-600 transition-colors"
                        >
                          Cancel
                        </button>
                      )}
                      <button
                        onClick={() => handleDownloadAction(download.id, 'delete')}
                        className="px-3 py-1 text-xs bg-gray-500 text-white rounded hover:bg-gray-600 transition-colors"
                      >
                        Delete
                      </button>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Footer */}
        <div className="p-6 border-t border-gray-200 bg-gray-50">
          <div className="flex justify-between items-center text-sm text-gray-500">
            <span>
              {sortedDownloads.length} download{sortedDownloads.length !== 1 ? 's' : ''}
            </span>
            <span>
              Total size: {formatFileSize(sortedDownloads.reduce((sum, d) => sum + (d.file_size || 0), 0))}
            </span>
          </div>
        </div>
      </div>
    </div>
  );
};
