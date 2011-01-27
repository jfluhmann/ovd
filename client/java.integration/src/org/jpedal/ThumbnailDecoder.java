/**
* ===========================================
* Java Pdf Extraction Decoding Access Library
* ===========================================
*
* Project Info:  http://www.jpedal.org
* (C) Copyright 1997-2008, IDRsolutions and Contributors.
*
* 	This file is part of JPedal
*
    This library is free software; you can redistribute it and/or
    modify it under the terms of the GNU General Public
    License as published by the Free Software Foundation; either
    version 2.1 of the License, or (at your option) any later version.

    This library is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
    General Public License for more details.

    You should have received a copy of the GNU General Public
    License along with this library; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA


*
* ---------------
* ThumbnailDecoder.java
* ---------------
*/
package org.jpedal;

import org.jpedal.io.ObjectStore;
import org.jpedal.objects.raw.PdfDictionary;
import org.jpedal.objects.raw.PdfObject;
import org.jpedal.objects.raw.PdfPageObject;
import org.jpedal.objects.raw.PdfResourcesObject;
import org.jpedal.parser.PdfStreamDecoder;
import org.jpedal.render.DynamicVectorRenderer;
import org.jpedal.utils.LogWriter;

import java.awt.image.BufferedImage;
import java.util.Map;

/**
 * generates thumbnails of pages for display
 */
public class ThumbnailDecoder {

	private PdfDecoder decode_pdf;

	private PdfStreamDecoder imageDecoder=null;

	private boolean stopDecoding=false;

	public ThumbnailDecoder(PdfDecoder decode_pdf) {

		this.decode_pdf=decode_pdf;

	}

	/**
	 * stop as soon as possible
	 */
	public void terminateDecoding(){

		stopDecoding=true;

		if(imageDecoder!=null){
			imageDecoder.terminateDecoding();

			//wait to die
			while (imageDecoder!=null && !imageDecoder.exitedDecoding()) {

				// System.out.println("Waiting to die");
				try {
					Thread.sleep(100);
				} catch (InterruptedException e) {
					// should never be called
					e.printStackTrace();
				}
			}
		}
	}


	/**
		 * get pdf as Image of any page scaling is size (100 = full size)
		 */
		final synchronized public BufferedImage getPageAsThumbnail(int pageNumber, int height) {

			stopDecoding=false;

			BufferedImage image = null;
			int mediaX, mediaY, mediaW, mediaH;

			/** the actual display object */
			DynamicVectorRenderer imageDisplay= ObjectStore.getCachedPage(new Integer(pageNumber));

			if(imageDisplay!=null){
				imageDisplay.setObjectStoreRef(decode_pdf.objectStoreRef);
			}else{
				imageDisplay = new DynamicVectorRenderer(pageNumber,true,
						1000, decode_pdf.objectStoreRef); //
				imageDisplay.setHiResImageForDisplayMode(decode_pdf.useHiResImageForDisplay);


				try {

					/** check in range */
					if (pageNumber > decode_pdf.getPageCount()) {

						LogWriter.writeLog("Page " + pageNumber + " out of bounds");

					} else {

						/** resolve page size */
						mediaX = decode_pdf.pageData.getMediaBoxX(pageNumber);
						mediaY = decode_pdf.pageData.getMediaBoxY(pageNumber);
						mediaW = decode_pdf.pageData.getMediaBoxWidth(pageNumber);
						mediaH = decode_pdf.pageData.getMediaBoxHeight(pageNumber);

						/** get pdf object id for page to decode */
						String currentPageOffset = (String) decode_pdf.pagesReferences.get(new Integer(pageNumber));

						/**
						 * decode the file if not already decoded, there is a valid
						 * object id and it is unencrypted
						 */
						if ((currentPageOffset != null)) {

							/** read page or next pages */
							PdfObject pdfObject=new PdfPageObject(currentPageOffset);
							decode_pdf.currentPdfFile.readObject(pdfObject, currentPageOffset,false, null);
							PdfObject Resources=pdfObject.getDictionary(PdfDictionary.Resources);

							if (pdfObject != null) {

								imageDecoder = new PdfStreamDecoder(decode_pdf.useHiResImageForDisplay);
								imageDecoder.setExternalImageRender(decode_pdf.customImageHandler);

								if(stopDecoding){
									imageDecoder=null;
									return null;
								}

								imageDecoder.setName(decode_pdf.filename);
								imageDecoder.setStore(decode_pdf.objectStoreRef);
								
								if(stopDecoding){
									imageDecoder=null;
									return null;
								}

								imageDecoder.init(true, true, PdfDecoder.renderMode, 0, decode_pdf.pageData,
										pageNumber, imageDisplay, decode_pdf.currentPdfFile);

								if (decode_pdf.globalResources != null)
									imageDecoder.readResources(decode_pdf.globalResources,true);

								/**read the resources for the page*/
								if (Resources != null)
									imageDecoder.readResources(Resources,true);

								if(stopDecoding){
									imageDecoder=null;
									return null;
								}


								imageDisplay.init(mediaW, mediaH, decode_pdf.pageData.getRotation(pageNumber));

								imageDecoder.decodePageContent(pdfObject, mediaX,mediaY, null, null);

							}
						}
					}
				} catch (Exception e) {
					e.printStackTrace();

				}

				imageDecoder=null;

				ObjectStore.cachePage(new Integer(pageNumber),imageDisplay);
			}

			/**
			 * workout scaling and get image
			 */
			if(!stopDecoding )
			image = decode_pdf.getImageFromRenderer(height, imageDisplay, pageNumber);


			return image;

		}

}
