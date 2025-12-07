import React, { useState } from 'react';
import { Download, Loader2 } from 'lucide-react';
import Button from '../common/Button';
import api from '../../api/axios';
import toast from 'react-hot-toast';

const ReceiptDownloadButton = ({ complaintId }) => {
  const [downloading, setDownloading] = useState(false);

  const handleDownload = async () => {
    if (!complaintId) {
      toast.error('Invalid complaint ID');
      return;
    }

    setDownloading(true);
    try {
      const response = await api.get(`/citizen/download-receipt.php?id=${complaintId}`, {
        responseType: 'blob',
      });

      // Create blob URL and trigger download
      const blob = new Blob([response.data], { type: 'application/pdf' });
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = `complaint_receipt_${complaintId}_${new Date().getTime()}.pdf`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      window.URL.revokeObjectURL(url);

      toast.success('Receipt downloaded successfully');
    } catch (error) {
      console.error('Download error:', error);
      toast.error(error.response?.data?.message || 'Failed to download receipt');
    } finally {
      setDownloading(false);
    }
  };

  return (
    <Button
      variant="outline"
      size="md"
      onClick={handleDownload}
      disabled={downloading || !complaintId}
    >
      {downloading ? (
        <>
          <Loader2 className="w-4 h-4 mr-2 animate-spin" />
          Downloading...
        </>
      ) : (
        <>
          <Download className="w-4 h-4 mr-2" />
          Download Receipt
        </>
      )}
    </Button>
  );
};

export default ReceiptDownloadButton;

