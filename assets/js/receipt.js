/**
 * Download Receipt - Shared functionality for wallet pages
 * Downloads the receipt as a PDF file
 */

function downloadReceipt() {
    if (typeof currentTxnData === 'undefined' || !currentTxnData) return;

    var t = currentTxnData.transaction;
    var p = currentTxnData.payment || {};
    var m = currentTxnData.milestone || {};
    var j = currentTxnData.job || {};
    var prefix = t.direction === 'credit' ? '+' : '';

    var script = document.createElement('script');
    script.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
    script.onload = function() {
        var jsPDF = window.jspdf.jsPDF;
        var doc = new jsPDF();

        var pageWidth = doc.internal.pageSize.getWidth();
        var margin = 20;
        var y = 20;

        doc.setFont('helvetica', 'normal');
        doc.setFontSize(10);
        doc.setTextColor(148, 163, 184);
        doc.text('JobHub', pageWidth / 2, y, { align: 'center' });
        y += 10;

        doc.setFontSize(24);
        doc.setFont('helvetica', 'bold');
        doc.setTextColor(30, 41, 59);
        doc.text(prefix + '$' + parseFloat(t.amount).toFixed(2), pageWidth / 2, y, { align: 'center' });
        y += 8;

        doc.setFontSize(12);
        doc.setFont('helvetica', 'normal');
        doc.setTextColor(100, 116, 139);
        doc.text(t.label, pageWidth / 2, y, { align: 'center' });
        y += 8;

        var badgeColors = {
            escrow_hold: [254, 243, 199],
            escrow_release: [219, 234, 254],
            deposit: [209, 250, 229],
            credit: [209, 250, 229],
            refund: [237, 233, 254],
            withdrawal: [254, 226, 226]
        };
        var badgeTextColors = {
            escrow_hold: [180, 83, 9],
            escrow_release: [29, 78, 216],
            deposit: [4, 120, 87],
            credit: [4, 120, 87],
            refund: [109, 40, 217],
            withdrawal: [220, 38, 38]
        };
        var bc = badgeColors[t.type] || [241, 245, 249];
        var tc = badgeTextColors[t.type] || [100, 116, 139];

        doc.setFillColor(bc[0], bc[1], bc[2]);
        doc.setTextColor(tc[0], tc[1], tc[2]);
        doc.setFontSize(9);
        doc.setFont('helvetica', 'bold');
        var badgeText = t.displayStatus;
        var badgeWidth = doc.getTextWidth(badgeText) + 12;
        doc.roundedRect(pageWidth / 2 - badgeWidth / 2, y - 3.5, badgeWidth, 7, 3.5, 3.5, 'F');
        doc.text(badgeText, pageWidth / 2, y, { align: 'center' });
        y += 10;

        doc.setDrawColor(226, 232, 240);
        doc.line(margin, y, pageWidth - margin, y);
        y += 10;

        doc.setFontSize(8);
        doc.setFont('helvetica', 'bold');
        doc.setTextColor(148, 163, 184);
        doc.text('TRANSACTION DETAILS', margin, y);
        y += 7;

        function addRow(key, val) {
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(10);
            doc.setTextColor(100, 116, 139);
            doc.text(key, margin, y);
            doc.setTextColor(30, 41, 59);
            doc.setFont('helvetica', 'bold');
            doc.text(val, pageWidth - margin, y, { align: 'right' });
            y += 7;
        }

        addRow('Date', t.dateFormatted);
        addRow('Transaction ID', '#' + t.id);
        addRow('Type', t.label);
        addRow('Balance After', '$' + parseFloat(t.balance_after).toFixed(2));
        y += 3;

        if (m.title || j.title) {
            doc.setDrawColor(226, 232, 240);
            doc.line(margin, y, pageWidth - margin, y);
            y += 10;
            doc.setFontSize(8);
            doc.setFont('helvetica', 'bold');
            doc.setTextColor(148, 163, 184);
            doc.text('PROJECT INFO', margin, y);
            y += 7;
            if (j.title) addRow('Project', j.title);
            if (m.title) addRow('Milestone', m.title);
            if (m.amount) addRow('Milestone Amount', '$' + parseFloat(m.amount).toFixed(2));
            y += 3;
        }

        if (p.payment_method) {
            doc.setDrawColor(226, 232, 240);
            doc.line(margin, y, pageWidth - margin, y);
            y += 10;
            doc.setFontSize(8);
            doc.setFont('helvetica', 'bold');
            doc.setTextColor(148, 163, 184);
            doc.text('PAYMENT INFO', margin, y);
            y += 7;
            addRow('Payment Method', p.payment_method.charAt(0).toUpperCase() + p.payment_method.slice(1));
            addRow('Gross Amount', '$' + parseFloat(p.total_amount).toFixed(2));
            addRow('Platform Fee', '-$' + parseFloat(p.platform_fee).toFixed(2));
            addRow('Freelancer Net', '$' + parseFloat(p.freelancer_net).toFixed(2));
        }

        var footerY = doc.internal.pageSize.getHeight() - 20;
        doc.setDrawColor(226, 232, 240);
        doc.line(margin, footerY, pageWidth - margin, footerY);
        doc.setFontSize(8);
        doc.setFont('helvetica', 'normal');
        doc.setTextColor(148, 163, 184);
        doc.text('This is a digital receipt from JobHub', pageWidth / 2, footerY + 7, { align: 'center' });

        doc.save('JobHub-Receipt-' + t.id + '.pdf');
    };
    document.head.appendChild(script);
}
