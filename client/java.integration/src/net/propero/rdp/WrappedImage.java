/* WrappedImage.java
 * Component: ProperJavaRDP
 * 
 * Revision: $Revision: 1.1.1.1 $
 * Author: $Author: suvarov $
 * Date: $Date: 2007/03/08 00:26:19 $
 *
 * Copyright (c) 2005 Propero Limited
 *
 * Purpose: Adds functionality to the BufferedImage class, allowing
 *          manipulation of colour indices, making the RGB values
 *          invisible (in the case of Indexed Colour only).
 */
package net.propero.rdp;

import java.awt.Graphics;
import java.awt.image.BufferedImage;
import java.awt.image.IndexColorModel;

import org.apache.log4j.Logger;

public class WrappedImage {
    static Logger logger = Logger.getLogger(RdesktopCanvas.class);
    IndexColorModel cm = null;
    BufferedImage bi = null;
    
    public WrappedImage(int arg0, int arg1, int arg2) {
        bi = new BufferedImage(arg0, arg1, arg2);
    }
    
    public WrappedImage(int arg0, int arg1, int arg2, IndexColorModel cm) {
        bi = new BufferedImage(arg0,arg1,BufferedImage.TYPE_INT_RGB); //super(arg0, arg1, BufferedImage.TYPE_INT_RGB);
        this.cm = cm;
    }
    
    public int getWidth(){ return bi.getWidth(); }
    public int getHeight(){ return bi.getHeight(); }
    
    public BufferedImage getBufferedImage(){ return bi; }
    
    public Graphics getGraphics(){
        return bi.getGraphics();
    }
    
    public BufferedImage getSubimage(int x,int y, int width, int height){
	int max_w = bi.getWidth();
	int max_h = bi.getHeight();

	if ((x + width) > max_w) {
		String msg = "[Warning] Bad width("+width+") replaced by ";
		width = max_w - x - 1;
		System.err.println(msg+width);
	}
	if ((y + height) > max_h) {
		String msg = "[Warning] Bad height("+height+") replaced by ";
		height = max_h - y - 1;
		System.err.println(msg+height);
	}

        return bi.getSubimage(x,y,width,height);
    }
    
    /**
     * Force a colour to its true RGB representation (extracting from colour model if indexed colour)
     * @param color
     * @return
     */
    public int checkColor(int color){
        if(cm != null) return cm.getRGB(color);
        return color;
    }
    
    /**
     * Set the colour model for this Image
     * @param cm Colour model for use with this image
     */
    public void setIndexColorModel(IndexColorModel cm){
        this.cm = cm;
    }
    
    public void setRGB(int x, int y, int color){
        x = Math.min(bi.getWidth()-1,x);
        x = Math.max(0,x);
        y = Math.min(bi.getHeight()-1,y);
        y = Math.max(0,y);
        //if(x >= bi.getWidth() || x < 0 || y >= bi.getHeight() || y < 0) return;
        
        if (cm != null) color = cm.getRGB(color);
        bi.setRGB(x,y,color);
    }

    /**
     * Apply a given array of colour values to an area of pixels in the image, do not convert for colour model
     * @param x x-coordinate for left of area to set
     * @param y y-coordinate for top of area to set
     * @param cx width of area to set
     * @param cy height of area to set
     * @param data array of pixel colour values to apply to area
     * @param offset offset to pixel data in data
     * @param w width of a line in data (measured in pixels)
     */
    public void setRGBNoConversion(int x, int y, int cx, int cy, int[] data, int offset,int w){
       bi.setRGB(x,y,cx,cy,data,offset,w);
    }
    
    public void setRGB(int x, int y, int cx, int cy, int[] data, int offset,int w){
        if(cm != null && data != null && data.length > 0){
            for(int i = 0; i < data.length; i++)
                data[i] = cm.getRGB(data[i]);
        }
        bi.setRGB(x,y,cx,cy,data,offset,w);
    }
    
    public int[] getRGB(int x,
            int y,
            int cx,
            int cy,
            int[] data,
            int offset,
            int width){
        return bi.getRGB(x,y,cx,cy,data,offset,width);
    }
      
    public int getRGB(int x, int y){
        //if(x >= this.getWidth() || x < 0 || y >= this.getHeight() || y < 0) return 0;
        
        if(cm == null) return bi.getRGB(x,y);
        else{
            int pix = bi.getRGB(x,y) & 0xFFFFFF;
            int[] vals = {(pix >> 16) & 0xFF,(pix >> 8) & 0xFF,(pix) & 0xFF};
            int out = cm.getDataElement(vals,0);
            if(cm.getRGB(out) != bi.getRGB(x,y)) logger.info("Did not get correct colour value for color (" + Integer.toHexString(pix) + "), got (" + cm.getRGB(out) + ") instead");
            return out;
        }
    }
    
}
